<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WhatsAppTemplateController extends Controller
{
    protected WhatsAppService $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * List all templates from local database.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WhatsAppTemplate::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $templates = $query->orderBy('updated_at', 'desc')->get();

        return response()->json([
            'message' => 'Templates retrieved successfully.',
            'error'   => false,
            'code'    => 200,
            'results' => $templates,
        ]);
    }

    /**
     * Create a new template (pushes to Meta + saves locally).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:512|regex:/^[a-z0-9_]+$/|unique:whatsapp_templates,name',
            'category' => 'required|string|in:MARKETING,UTILITY,AUTHENTICATION',
            'variable_type' => 'nullable|string|in:name,number',
            'language' => 'required|string|max:10',
            'body_text' => 'required|string|max:1024',
            'header_type'    => 'nullable|string|in:none,text',
            'header_content' => 'nullable|string|max:60',
            'footer_text'    => 'nullable|string|max:60',
            'buttons'         => 'nullable|array',
            'variable_samples' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error.',
                'error'   => true,
                'code'    => 422,
                'results' => $validator->errors(),
            ], 422);
        }

        // Build the template locally first to generate components
        $template = new WhatsAppTemplate($request->only([
            'name', 'category', 'variable_type', 'language', 'body_text',
            'header_type', 'header_content', 'footer_text',
            'buttons', 'variable_samples',
        ]));

        $components = $template->buildComponentsForApi();

        // Push to Meta Cloud API
        $result = $this->whatsAppService->createTemplate([
            'name'            => $request->name,
            'category'        => $request->category,
            'variable_type'   => $request->variable_type ?? 'number',
            'language'        => $request->language,
            'components'      => $components,
        ]);

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to create template on Meta: ' . ($result['error'] ?? 'Unknown error'),
                'error'   => true,
                'code'    => 422,
                'results' => $result['details'] ?? null,
            ], 422);
        }

        // Save locally with Meta response
        $template->meta_template_id = $result['template_id'] ?? null;
        $template->status = $result['status'] ?? 'PENDING';
        $template->components = $components;
        $template->last_synced_at = now();
        $template->save();

        return response()->json([
            'message' => 'Template created and submitted to Meta for review.',
            'error'   => false,
            'code'    => 201,
            'results' => $template->fresh(),
        ], 201);
    }

    /**
     * Show a single template.
     */
    public function show(int $id): JsonResponse
    {
        $template = WhatsAppTemplate::find($id);

        if (!$template) {
            return response()->json([
                'message' => 'Template not found.',
                'error'   => true,
                'code'    => 404,
                'results' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Template retrieved successfully.',
            'error'   => false,
            'code'    => 200,
            'results' => $template,
        ]);
    }

    /**
     * Update a template (pushes to Meta + updates locally).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = WhatsAppTemplate::find($id);

        if (!$template) {
            return response()->json([
                'message' => 'Template not found.',
                'error'   => true,
                'code'    => 404,
                'results' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'category'    => 'nullable|string|in:MARKETING,UTILITY,AUTHENTICATION',
            'variable_type' => 'nullable|string|in:name,number',
            'body_text'   => 'required|string|max:1024',
            'header_type'      => 'nullable|string|in:none,text',
            'header_content'   => 'nullable|string|max:60',
            'footer_text'      => 'nullable|string|max:60',
            'buttons'          => 'nullable|array',
            'variable_samples' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error.',
                'error'   => true,
                'code'    => 422,
                'results' => $validator->errors(),
            ], 422);
        }

        // Update local fields for component building
        $template->fill($request->only([
            'category', 'variable_type', 'body_text', 'header_type',
            'header_content', 'footer_text', 'buttons', 'variable_samples',
        ]));

        $components = $template->buildComponentsForApi();

        // Push to Meta if we have a meta_template_id
        if ($template->meta_template_id) {
            $result = $this->whatsAppService->editTemplate($template->meta_template_id, [
                'components' => $components,
                'category'   => $request->category ?? $template->category,
            ]);

            if (!$result['success']) {
                return response()->json([
                    'message' => 'Failed to update template on Meta: ' . ($result['error'] ?? 'Unknown error'),
                    'error'   => true,
                    'code'    => 422,
                    'results' => $result['details'] ?? null,
                ], 422);
            }
        }

        $template->components = $components;
        $template->last_synced_at = now();
        $template->save();

        return response()->json([
            'message' => 'Template updated successfully.',
            'error'   => false,
            'code'    => 200,
            'results' => $template->fresh(),
        ]);
    }

    /**
     * Delete a template (removes from Meta + locally).
     */
    public function destroy(int $id): JsonResponse
    {
        $template = WhatsAppTemplate::find($id);

        if (!$template) {
            return response()->json([
                'message' => 'Template not found.',
                'error'   => true,
                'code'    => 404,
                'results' => null,
            ], 404);
        }

        // Delete from Meta Cloud API
        $result = $this->whatsAppService->deleteTemplate($template->name);

        if (!$result['success']) {
            Log::warning('WhatsApp: Failed to delete template from Meta, removing locally only.', [
                'template' => $template->name,
                'error'    => $result['error'] ?? 'Unknown',
            ]);
        }

        $template->delete();

        return response()->json([
            'message' => 'Template deleted successfully.',
            'error'   => false,
            'code'    => 200,
            'results' => null,
        ]);
    }

    /**
     * Sync all templates from Meta Cloud API to local database.
     */
    public function sync(): JsonResponse
    {
        $count = $this->whatsAppService->syncTemplatesFromMeta();

        return response()->json([
            'message' => "{$count} templates synced from Meta successfully.",
            'error'   => false,
            'code'    => 200,
            'results' => ['synced_count' => $count],
        ]);
    }
}
