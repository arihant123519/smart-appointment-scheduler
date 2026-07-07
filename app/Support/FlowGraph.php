<?php

namespace App\Support;

/**
 * Converts a Drawflow editor export into the normalized graph shape FlowEngine
 * executes: `{start: "<node_id>", nodes: {"<id>": {type, data, next}}}`.
 *
 * This is the only place Drawflow's export shape is known — FlowEngine and
 * every other consumer only ever see the normalized `graph`. Node type comes
 * from Drawflow's own `name` field (set when the palette adds a node via
 * `editor.addNode(name, ...)`); per-node config comes from Drawflow's `data`
 * field (edited via the builder's side panel).
 *
 * `branch_yes_no` nodes use two output ports: `output_1` → next.yes,
 * `output_2` → next.no. Every other node type has a single `output_1` → next.default.
 *
 * `comment` nodes are freeform annotations (documentation, notes) with no
 * ports — they're dropped entirely from the executable graph so FlowEngine
 * never sees them and they can never be picked as the start node.
 */
class FlowGraph
{
    public const BRANCH_TYPE = 'branch_yes_no';
    public const COMMENT_TYPE = 'comment';

    /**
     * @param  array<string, mixed>  $export  raw `editor.export()` output
     * @return array{start: ?string, nodes: array<string, array{type: string, data: array, next: array<string, string>}>}
     */
    public static function fromDrawflowExport(array $export): array
    {
        $rawNodes = $export['drawflow']['Home']['data'] ?? [];

        $nodes = [];
        $referenced = [];

        foreach ($rawNodes as $id => $node) {
            $id = (string) $id;
            $type = (string) ($node['name'] ?? 'send_message');

            if ($type === self::COMMENT_TYPE) {
                continue;
            }

            $data = (array) ($node['data'] ?? []);
            $outputs = (array) ($node['outputs'] ?? []);

            $next = $type === self::BRANCH_TYPE
                ? [
                    'yes' => self::targetOf($outputs, 'output_1'),
                    'no' => self::targetOf($outputs, 'output_2'),
                ]
                : ['default' => self::targetOf($outputs, 'output_1')];

            $next = array_filter($next, fn ($v) => $v !== null);
            foreach ($next as $targetId) {
                $referenced[$targetId] = true;
            }

            $nodes[$id] = ['type' => $type, 'data' => $data, 'next' => $next];
        }

        return [
            'start' => self::findStart($nodes, $referenced),
            'nodes' => $nodes,
        ];
    }

    /** @param  array<string, mixed>  $outputs */
    private static function targetOf(array $outputs, string $port): ?string
    {
        $target = $outputs[$port]['connections'][0]['node'] ?? null;

        return $target !== null ? (string) $target : null;
    }

    /**
     * The start node is whichever node nothing else points to. If every node
     * is referenced (a cyclic or malformed graph) or there are no nodes,
     * fall back to the lowest numeric id, or null when the graph is empty.
     *
     * @param  array<string, array>  $nodes
     * @param  array<string, bool>  $referenced
     */
    private static function findStart(array $nodes, array $referenced): ?string
    {
        if (empty($nodes)) {
            return null;
        }

        foreach (array_keys($nodes) as $id) {
            if (! isset($referenced[$id])) {
                return $id;
            }
        }

        $ids = array_keys($nodes);
        sort($ids, SORT_NUMERIC);

        return $ids[0];
    }
}
