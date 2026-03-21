<?php

namespace App\Http\Controllers;

use App\Models\ContactTypeModel;
use Illuminate\Http\Request;

class ContactTypeController extends Controller
{
    /**
     * List all contact types (active only for non-admin, all for admin).
     */
    public function index(Request $request)
    {
        if ($request->user()?->isAdmin() && $request->has('all')) {
            return response()->json(
                ContactTypeModel::orderBy('sort_order')->get()
            );
        }

        return response()->json(ContactTypeModel::allActive());
    }

    /**
     * Create a new contact type (admin only).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'value'      => 'required|string|max:50|unique:contact_types,value|regex:/^[a-z0-9_]+$/',
            'label'      => 'required|string|max:100',
            'icon'       => 'required|string|max:10',
            'color'      => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $data['sort_order'] = $data['sort_order'] ?? (ContactTypeModel::max('sort_order') + 1);

        $type = ContactTypeModel::create($data);
        ContactTypeModel::flushCache();

        return response()->json($type, 201);
    }

    /**
     * Update a contact type (admin only).
     */
    public function update(Request $request, ContactTypeModel $contactType)
    {
        $data = $request->validate([
            'label'      => 'sometimes|string|max:100',
            'icon'       => 'sometimes|string|max:10',
            'color'      => 'sometimes|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active'  => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $contactType->update($data);
        ContactTypeModel::flushCache();

        return response()->json($contactType);
    }

    /**
     * Delete a contact type (admin only).
     * Only allowed if no contacts use this type.
     */
    public function destroy(ContactTypeModel $contactType)
    {
        $count = \App\Models\Influenceur::where('contact_type', $contactType->value)->count();
        if ($count > 0) {
            return response()->json([
                'message' => "Impossible de supprimer : {$count} contact(s) utilisent ce type.",
            ], 422);
        }

        $contactType->delete();
        ContactTypeModel::flushCache();

        return response()->json(null, 204);
    }
}
