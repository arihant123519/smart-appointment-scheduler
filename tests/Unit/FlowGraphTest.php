<?php

namespace Tests\Unit;

use App\Support\FlowGraph;
use Tests\TestCase;

/**
 * FlowGraph::fromDrawflowExport() is the only place Drawflow's raw export
 * shape is understood — FlowEngine only ever sees the normalized graph this
 * produces. A branch_yes_no node's two output ports must map to next.yes /
 * next.no; every other node type maps its single output to next.default.
 */
class FlowGraphTest extends TestCase
{
    public function test_it_normalizes_a_branching_drawflow_export(): void
    {
        $export = [
            'drawflow' => [
                'Home' => [
                    'data' => [
                        '1' => [
                            'id' => 1,
                            'name' => 'send_message',
                            'data' => ['mode' => 'text', 'text' => 'Does the new time work?'],
                            'inputs' => ['input_1' => ['connections' => []]],
                            'outputs' => ['output_1' => ['connections' => [['node' => '2', 'output' => 'input_1']]]],
                        ],
                        '2' => [
                            'id' => 2,
                            'name' => 'branch_yes_no',
                            'data' => ['unclear_message' => 'Please reply yes or no.'],
                            'inputs' => ['input_1' => ['connections' => [['node' => '1', 'input' => 'output_1']]]],
                            'outputs' => [
                                'output_1' => ['connections' => [['node' => '3', 'output' => 'input_1']]],
                                'output_2' => ['connections' => [['node' => '4', 'output' => 'input_1']]],
                            ],
                        ],
                        '3' => [
                            'id' => 3,
                            'name' => 'end',
                            'data' => ['outcome' => 'confirmed'],
                            'inputs' => ['input_1' => ['connections' => [['node' => '2', 'input' => 'output_1']]]],
                            'outputs' => [],
                        ],
                        '4' => [
                            'id' => 4,
                            'name' => 'end',
                            'data' => ['outcome' => 'declined'],
                            'inputs' => ['input_1' => ['connections' => [['node' => '2', 'input' => 'output_2']]]],
                            'outputs' => [],
                        ],
                    ],
                ],
            ],
        ];

        $graph = FlowGraph::fromDrawflowExport($export);

        $this->assertSame('1', $graph['start']);
        $this->assertSame('send_message', $graph['nodes']['1']['type']);
        $this->assertSame(['default' => '2'], $graph['nodes']['1']['next']);
        $this->assertSame('branch_yes_no', $graph['nodes']['2']['type']);
        $this->assertSame(['yes' => '3', 'no' => '4'], $graph['nodes']['2']['next']);
        $this->assertSame('end', $graph['nodes']['3']['type']);
        $this->assertSame('confirmed', $graph['nodes']['3']['data']['outcome']);
        $this->assertSame([], $graph['nodes']['4']['next']);
    }

    public function test_empty_export_yields_no_start(): void
    {
        $graph = FlowGraph::fromDrawflowExport(['drawflow' => ['Home' => ['data' => []]]]);

        $this->assertNull($graph['start']);
        $this->assertSame([], $graph['nodes']);
    }

    public function test_comment_nodes_are_excluded_from_the_executable_graph(): void
    {
        $export = [
            'drawflow' => [
                'Home' => [
                    'data' => [
                        '1' => [
                            'id' => 1,
                            'name' => 'send_message',
                            'data' => ['mode' => 'text', 'text' => 'Hello'],
                            'inputs' => ['input_1' => ['connections' => []]],
                            'outputs' => ['output_1' => ['connections' => []]],
                        ],
                        '2' => [
                            'id' => 2,
                            'name' => 'comment',
                            'data' => ['text' => 'Reminder: staff double-checks this manually'],
                            'inputs' => [],
                            'outputs' => [],
                        ],
                    ],
                ],
            ],
        ];

        $graph = FlowGraph::fromDrawflowExport($export);

        $this->assertSame('1', $graph['start']);
        $this->assertArrayNotHasKey('2', $graph['nodes']);
        $this->assertCount(1, $graph['nodes']);
    }
}
