<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    /**
     * GET /api/admin/services
     *
     * Retourne la liste de tous les services avec leur département et leur chef.
     *
     * Middleware: auth:sanctum, role:admin
     * Headers: Authorization: Bearer {token}, Accept: application/json
     */
    public function index(): JsonResponse
    {
        $services = Service::with('department', 'chefService', 'users')->get();
        return response()->json($services);
    }

    /**
     * POST /api/admin/services
     *
     * Crée un nouveau service.
     *
     * Request Body:
     *   { "name": "IT Support", "department_id": 1, "chef_service_id": 2 }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'department_id'   => 'required|exists:departments,id',
            'chef_service_id' => 'nullable|exists:users,id',
        ]);

        $service = Service::create($validated);
        return response()->json($service->load('department', 'chefService'), 201);
    }

    /**
     * GET /api/admin/services/{id}
     *
     * Afficher les détails d'un service.
     */
    public function show(Service $service): JsonResponse
    {
        return response()->json($service->load('department', 'chefService', 'users'));
    }

    /**
     * PUT /api/admin/services/{id}
     *
     * Modifier un service.
     * Request Body: { "name": "...", "department_id": 1, "chef_service_id": 2 }
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'department_id'   => 'sometimes|exists:departments,id',
            'chef_service_id' => 'nullable|exists:users,id',
        ]);

        $service->update($validated);
        return response()->json($service->fresh('department', 'chefService'));
    }

    /**
     * DELETE /api/admin/services/{id}
     *
     * Supprime un service.
     */
    public function destroy(Service $service): JsonResponse
    {
        $service->delete();
        return response()->json(['message' => 'Service deleted.']);
    }
}
