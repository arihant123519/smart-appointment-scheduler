<?php

namespace App\Http\Controllers;

use App\Models\WhatsappFlow;
use App\Support\FlowGraph;
use App\Support\WhatsappTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * CRUD + activation for WhatsApp conversation flows, authored visually in the
 * flow builder (resources/views/flows/edit.blade.php, a Drawflow canvas).
 * Drawflow's raw export is normalized server-side into the executable `graph`
 * (see App\Support\FlowGraph) so the JS side never needs to know FlowEngine's
 * node contract.
 */
class WhatsappFlowController extends Controller
{
    public function index(): View
    {
        $flows = WhatsappFlow::forCurrentClinic()->withCount('conversations')->latest()->get();
        $events = WhatsappTemplate::events();

        return view('flows.index', compact('flows', 'events'));
    }

    public function create(): View
    {
        $flow = new WhatsappFlow(['status' => 'draft', 'graph' => null, 'canvas_json' => null]);
        $events = WhatsappTemplate::events();
        $templateSections = $this->templateSections();

        return view('flows.edit', compact('flow', 'events', 'templateSections'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateFlow($request);

        $flow = WhatsappFlow::create([
            'clinic_id' => auth()->user()->clinic_id,
            'name' => $data['name'],
            'trigger_event' => $data['trigger_event'] ?? null,
            'canvas_json' => $data['canvas_json'],
            'graph' => FlowGraph::fromDrawflowExport($data['canvas_json']),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('flows.edit', $flow)->with('success', 'Flow saved as a draft. Activate it when you\'re ready to go live.');
    }

    public function edit(WhatsappFlow $flow): View
    {
        $events = WhatsappTemplate::events();
        $templateSections = $this->templateSections();

        return view('flows.edit', compact('flow', 'events', 'templateSections'));
    }

    public function update(Request $request, WhatsappFlow $flow): RedirectResponse
    {
        $data = $this->validateFlow($request);

        $flow->update([
            'name' => $data['name'],
            'trigger_event' => $data['trigger_event'] ?? null,
            'canvas_json' => $data['canvas_json'],
            'graph' => FlowGraph::fromDrawflowExport($data['canvas_json']),
            'version' => $flow->version + 1,
        ]);

        return redirect()->route('flows.edit', $flow)->with('success', 'Flow updated.');
    }

    public function activate(WhatsappFlow $flow): RedirectResponse
    {
        if (! $flow->trigger_event) {
            return back()->with('error', 'Set a trigger event before activating this flow.');
        }

        if (empty($flow->graph['nodes'])) {
            return back()->with('error', 'This flow has no steps yet — build it on the canvas first.');
        }

        $flow->activate();

        return back()->with('success', "\"{$flow->name}\" is now the active flow for its trigger event.");
    }

    public function destroy(WhatsappFlow $flow): RedirectResponse
    {
        $flow->delete();

        return redirect()->route('flows.index')->with('success', 'Flow deleted.');
    }

    public function conversations(WhatsappFlow $flow): View
    {
        $conversations = $flow->conversations()
            ->with(['patient', 'appointment'])
            ->latest('started_at')
            ->paginate(20);

        return view('flows.conversations', compact('flow', 'conversations'));
    }

    /**
     * Configured WhatsApp template bodies, keyed by event — used to auto-fill
     * a "Send message" step's plain-text fallback with the real template
     * wording (converted to named {{token}} placeholders) once an event is picked.
     *
     * @return array<string, array{body: string, variables: array<int, string>}>
     */
    private function templateSections(): array
    {
        return collect(WhatsappTemplate::sectionsForEditing())
            ->keyBy('event')
            ->map(fn ($section) => ['body' => $section['body'], 'variables' => $section['variables']])
            ->all();
    }

    /** @return array{name: string, trigger_event: ?string, canvas_json: array} */
    private function validateFlow(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger_event' => ['nullable', Rule::in(WhatsappTemplate::eventKeys())],
            'canvas_json' => ['required', 'string'],
        ]);

        $decoded = json_decode($data['canvas_json'], true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['canvas_json' => 'The flow canvas could not be read — try saving again.']);
        }

        $data['canvas_json'] = $decoded;

        return $data;
    }
}
