<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Nutrition\Models\FoodItem;

/**
 * A small bilingual (en/ar) starter food database for dev/demo (FR-NUT-001). Macros are
 * per serving (serving described in serving_units). The full licensed, Arabic-localized
 * food DB is an external dependency (Q4, MASTER §12). Idempotent: keyed on barcode.
 */
class FoodLibrarySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->foods() as $food) {
            FoodItem::updateOrCreate(['barcode' => $food['barcode']], $food);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function foods(): array
    {
        return [
            ['barcode' => 'SEED-CHICKEN', 'slug' => 'chicken-breast', 'name_i18n' => ['en' => 'Chicken Breast', 'ar' => 'صدر دجاج'], 'serving_units' => [['label' => '100g', 'grams' => 100]], 'kcal' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6, 'dietary_tags' => []],
            ['barcode' => 'SEED-RICE', 'slug' => 'white-rice-cooked', 'name_i18n' => ['en' => 'White Rice (cooked)', 'ar' => 'أرز أبيض مطبوخ'], 'serving_units' => [['label' => '100g', 'grams' => 100]], 'kcal' => 130, 'protein' => 2.7, 'carbs' => 28, 'fat' => 0.3, 'dietary_tags' => []],
            ['barcode' => 'SEED-EGG', 'slug' => 'egg', 'name_i18n' => ['en' => 'Egg', 'ar' => 'بيضة'], 'serving_units' => [['label' => '1 large', 'grams' => 50]], 'kcal' => 78, 'protein' => 6.3, 'carbs' => 0.6, 'fat' => 5.3, 'dietary_tags' => ['egg']],
            ['barcode' => 'SEED-OATS', 'slug' => 'rolled-oats', 'name_i18n' => ['en' => 'Rolled Oats', 'ar' => 'شوفان'], 'serving_units' => [['label' => '40g', 'grams' => 40]], 'kcal' => 150, 'protein' => 5, 'carbs' => 27, 'fat' => 2.5, 'dietary_tags' => ['gluten']],
            ['barcode' => 'SEED-BANANA', 'slug' => 'banana', 'name_i18n' => ['en' => 'Banana', 'ar' => 'موز'], 'serving_units' => [['label' => '1 medium', 'grams' => 118]], 'kcal' => 105, 'protein' => 1.3, 'carbs' => 27, 'fat' => 0.4, 'dietary_tags' => []],
            ['barcode' => 'SEED-LENTILS', 'slug' => 'lentils-cooked', 'name_i18n' => ['en' => 'Lentils (cooked)', 'ar' => 'عدس مطبوخ'], 'serving_units' => [['label' => '100g', 'grams' => 100]], 'kcal' => 116, 'protein' => 9, 'carbs' => 20, 'fat' => 0.4, 'dietary_tags' => []],
            ['barcode' => 'SEED-YOGURT', 'slug' => 'greek-yogurt', 'name_i18n' => ['en' => 'Greek Yogurt', 'ar' => 'زبادي يوناني'], 'serving_units' => [['label' => '170g', 'grams' => 170]], 'kcal' => 100, 'protein' => 17, 'carbs' => 6, 'fat' => 0.7, 'dietary_tags' => ['dairy']],
            ['barcode' => 'SEED-OLIVEOIL', 'slug' => 'olive-oil', 'name_i18n' => ['en' => 'Olive Oil', 'ar' => 'زيت زيتون'], 'serving_units' => [['label' => '1 tbsp', 'grams' => 14]], 'kcal' => 119, 'protein' => 0, 'carbs' => 0, 'fat' => 14, 'dietary_tags' => []],
        ];
    }
}
