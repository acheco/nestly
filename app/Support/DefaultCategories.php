<?php

namespace App\Support;

use App\Enums\CategoryKind;

class DefaultCategories
{
    /**
     * @return array<int, array{name: string, icon: string, color: string, kind: CategoryKind}>
     */
    public static function all(): array
    {
        return [
            // Gastos — colores tomados de la paleta categórica del Design System (§6.2.1)
            ['name' => 'Comida', 'icon' => 'utensils', 'color' => '#F59E0B', 'kind' => CategoryKind::Expense],
            ['name' => 'Transporte', 'icon' => 'car', 'color' => '#4F7CFF', 'kind' => CategoryKind::Expense],
            ['name' => 'Vivienda', 'icon' => 'home', 'color' => '#A855F7', 'kind' => CategoryKind::Expense],
            ['name' => 'Servicios', 'icon' => 'zap', 'color' => '#06B6D4', 'kind' => CategoryKind::Expense],
            ['name' => 'Entretenimiento', 'icon' => 'film', 'color' => '#EC4899', 'kind' => CategoryKind::Expense],
            ['name' => 'Salud', 'icon' => 'heart-pulse', 'color' => '#EF4444', 'kind' => CategoryKind::Expense],
            ['name' => 'Compras', 'icon' => 'shopping-bag', 'color' => '#84CC16', 'kind' => CategoryKind::Expense],
            ['name' => 'Otros gastos', 'icon' => 'ellipsis', 'color' => '#22C55E', 'kind' => CategoryKind::Expense],
            // Ingresos
            ['name' => 'Salario', 'icon' => 'banknote', 'color' => '#22C55E', 'kind' => CategoryKind::Income],
            ['name' => 'Freelance', 'icon' => 'laptop', 'color' => '#4F7CFF', 'kind' => CategoryKind::Income],
            ['name' => 'Inversiones', 'icon' => 'trending-up', 'color' => '#A855F7', 'kind' => CategoryKind::Income],
            ['name' => 'Otros ingresos', 'icon' => 'plus-circle', 'color' => '#06B6D4', 'kind' => CategoryKind::Income],
        ];
    }
}
