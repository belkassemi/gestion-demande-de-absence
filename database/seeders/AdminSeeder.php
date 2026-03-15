<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Departments
        $departments = [
            'Département des Études',
            'Département des Affaires Juridiques et Foncières',
            'Département de la Gestion Urbaine',
            'Département Administratif et Financier',
            'Service Informatique'
        ];

        foreach ($departments as $deptName) {
            \App\Models\Department::firstOrCreate(['name' => $deptName]);
        }

        // 2. Seed Absence Types
        $absenceTypes = [
            ['name' => 'Congé annuel', 'requires_document' => false, 'color' => '#10B981'],
            ['name' => 'Congé maladie', 'requires_document' => true, 'color' => '#EF4444'],
            ['name' => 'Permission', 'requires_document' => false, 'color' => '#F59E0B'],
            ['name' => 'Formation professionnelle', 'requires_document' => false, 'color' => '#3B82F6'],
            ['name' => 'Congé maternité', 'requires_document' => true, 'color' => '#EC4899'],
            ['name' => 'Congé paternité', 'requires_document' => true, 'color' => '#8B5CF6'],
            ['name' => 'Congé sans solde', 'requires_document' => false, 'color' => '#6B7280'],
            ['name' => 'Récupération', 'requires_document' => false, 'color' => '#14B8A6'],
            ['name' => 'Événement familial', 'requires_document' => true, 'color' => '#F97316'],
            ['name' => 'Mission', 'requires_document' => false, 'color' => '#06B6D4'],
            ['name' => 'Télétravail', 'requires_document' => false, 'color' => '#84CC16'],
            ['name' => 'Accident de travail', 'requires_document' => true, 'color' => '#DC2626'],
        ];

        foreach ($absenceTypes as $type) {
            \App\Models\AbsenceType::firstOrCreate(
                ['name' => $type['name']],
                ['requires_document' => $type['requires_document'], 'color' => $type['color']]
            );
        }

        // 3. Seed Admin User
        User::firstOrCreate(
            ['email' => 'aulaayoune@gmail.com'],
            [
                'name'     => 'System Administrator',
                'password' => Hash::make('aulaayoune@gmail.com'),
                'role'     => 'admin',
            ]
        );
    }
}
