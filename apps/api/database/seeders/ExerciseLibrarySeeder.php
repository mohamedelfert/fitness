<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Training\Models\Exercise;

/**
 * A small bilingual (en/ar) starter exercise library for dev/demo (FR-TRN-001/006).
 * The full licensed, Arabic-localized dataset is an external dependency (Q4, MASTER §12).
 * Idempotent: keyed on slug so re-seeding updates rather than duplicates.
 */
class ExerciseLibrarySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->library() as $exercise) {
            Exercise::updateOrCreate(['slug' => $exercise['slug']], $exercise);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function library(): array
    {
        return [
            [
                'slug' => 'barbell-bench-press', 'name' => 'Barbell Bench Press',
                'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'front_delts'],
                'equipment' => ['barbell', 'bench'], 'mechanics' => 'compound',
                'instructions' => ['en' => 'Lower the bar to mid-chest, then press up.', 'ar' => 'أنزل البار إلى منتصف الصدر ثم ادفع لأعلى.'],
                'contraindications' => ['shoulder_impingement'],
            ],
            [
                'slug' => 'back-squat', 'name' => 'Back Squat',
                'primary_muscles' => ['quads', 'glutes'], 'secondary_muscles' => ['hamstrings', 'core'],
                'equipment' => ['barbell', 'rack'], 'mechanics' => 'compound',
                'instructions' => ['en' => 'Descend to at least parallel, drive through mid-foot.', 'ar' => 'انزل حتى الموازاة على الأقل وادفع من منتصف القدم.'],
                'contraindications' => ['knee_injury', 'lower_back_injury'],
            ],
            [
                'slug' => 'conventional-deadlift', 'name' => 'Conventional Deadlift',
                'primary_muscles' => ['hamstrings', 'glutes', 'back'], 'secondary_muscles' => ['forearms', 'core'],
                'equipment' => ['barbell'], 'mechanics' => 'compound',
                'instructions' => ['en' => 'Brace, keep the bar close, extend hips and knees together.', 'ar' => 'ثبّت جذعك وأبقِ البار قريبًا ومدّ الورك والركبة معًا.'],
                'contraindications' => ['lower_back_injury', 'herniated_disc'],
            ],
            [
                'slug' => 'overhead-press', 'name' => 'Overhead Press',
                'primary_muscles' => ['shoulders'], 'secondary_muscles' => ['triceps', 'core'],
                'equipment' => ['barbell'], 'mechanics' => 'compound',
                'instructions' => ['en' => 'Press the bar overhead, ribs down, glutes tight.', 'ar' => 'ادفع البار فوق الرأس مع شدّ المؤخرة وثبات الأضلاع.'],
                'contraindications' => ['shoulder_impingement'],
            ],
            [
                'slug' => 'pull-up', 'name' => 'Pull-up',
                'primary_muscles' => ['back', 'lats'], 'secondary_muscles' => ['biceps'],
                'equipment' => ['pull_up_bar', 'bodyweight'], 'mechanics' => 'compound',
                'instructions' => ['en' => 'Pull until chin clears the bar, control the descent.', 'ar' => 'اسحب حتى يتجاوز الذقن العارضة وتحكم في النزول.'],
                'contraindications' => [],
            ],
            [
                'slug' => 'push-up', 'name' => 'Push-up',
                'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'core'],
                'equipment' => ['bodyweight'], 'mechanics' => 'compound',
                'instructions' => ['en' => 'Keep a straight line head to heels; lower under control.', 'ar' => 'حافظ على استقامة الجسم من الرأس للكعب وانزل بتحكم.'],
                'contraindications' => ['wrist_injury'],
            ],
            [
                'slug' => 'dumbbell-row', 'name' => 'Dumbbell Row',
                'primary_muscles' => ['back', 'lats'], 'secondary_muscles' => ['biceps'],
                'equipment' => ['dumbbells', 'bench'], 'mechanics' => 'compound',
                'instructions' => ['en' => 'Row the dumbbell to the hip, keep the spine neutral.', 'ar' => 'اسحب الدمبل نحو الورك مع إبقاء العمود الفقري محايدًا.'],
                'contraindications' => [],
            ],
            [
                'slug' => 'romanian-deadlift', 'name' => 'Romanian Deadlift',
                'primary_muscles' => ['hamstrings', 'glutes'], 'secondary_muscles' => ['back'],
                'equipment' => ['barbell', 'dumbbells'], 'mechanics' => 'compound',
                'instructions' => ['en' => 'Hinge at the hips with soft knees, feel the hamstring stretch.', 'ar' => 'انحنِ من الورك مع ثني بسيط للركبة واشعر بتمدد الفخذ الخلفي.'],
                'contraindications' => ['lower_back_injury'],
            ],
            [
                'slug' => 'plank', 'name' => 'Plank',
                'primary_muscles' => ['core'], 'secondary_muscles' => ['shoulders'],
                'equipment' => ['bodyweight'], 'mechanics' => 'isolation',
                'instructions' => ['en' => 'Hold a rigid line on forearms; do not let the hips sag.', 'ar' => 'ثبّت الجسم على الساعدين دون هبوط الورك.'],
                'contraindications' => [],
            ],
            [
                'slug' => 'goblet-squat', 'name' => 'Goblet Squat',
                'primary_muscles' => ['quads', 'glutes'], 'secondary_muscles' => ['core'],
                'equipment' => ['dumbbells', 'kettlebell'], 'mechanics' => 'compound',
                'instructions' => ['en' => 'Hold the weight at the chest and squat between the knees.', 'ar' => 'أمسك الوزن أمام الصدر واهبط بين الركبتين.'],
                'contraindications' => ['knee_injury'],
            ],
        ];
    }
}
