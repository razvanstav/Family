<?php

namespace Database\Seeders;

use App\Modules\Kitchen\Application\RecipeCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class KitchenDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('memoria.demo_enabled')) {
            throw new \RuntimeException('Exemplele sunt permise doar în mediul demo local sau de test.');
        }
        DB::transaction(function () {
            $family = DB::table('families')->where('id', DemoSeeder::POPESCU)->lockForUpdate()->first();
            $owner = DB::table('family_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
                ->where('m.family_id', DemoSeeder::POPESCU)->where('u.email', 'ana@example.test')
                ->where('m.role', 'owner')->whereNull('m.revoked_at')->value('u.id');
            if (! $family || ! $owner) {
                throw new \RuntimeException('Familia demo existentă este necesară; nu se modifică datele de identitate.');
            }
            DB::select("SELECT set_config('app.family_id', ?, true)", [DemoSeeder::POPESCU]);
            if (DB::table('recipes')->where('family_id', DemoSeeder::POPESCU)->where('is_example', true)->exists()) {
                return;
            }
            $samples = [
                ['title' => 'Paste cremoase cu ciuperci', 'category' => 'main', 'duration_minutes' => 30, 'servings' => 4, 'difficulty' => 'easy', 'photo' => 'pasta.png',
                    'ingredients' => ['400 g tagliatelle', '400 g ciuperci', '200 ml smântână pentru gătit', '2 căței de usturoi', '40 g parmezan', '2 linguri ulei de măsline', 'Pătrunjel, sare și piper'],
                    'instructions' => "Fierbe pastele conform instrucțiunilor de pe ambalaj. Păstrează o cană din apa de fierbere.\n\nTaie ciupercile și rumenește-le în ulei. Adaugă usturoiul tocat, apoi smântâna și câteva linguri din apa pastelor.\n\nAdaugă pastele și parmezanul. Amestecă și potrivește de sare și piper. Servește cu pătrunjel."],
                ['title' => 'Ciorbă de legume', 'category' => 'soup', 'duration_minutes' => 60, 'servings' => 6, 'difficulty' => 'easy', 'photo' => 'soup.png',
                    'ingredients' => ['1 ceapă', '2 morcovi', '300 g varză', '2 cartofi', '400 g roșii tocate', '2 linguri ulei', '2 l apă', 'Mărar, zeamă de lămâie, sare și piper'],
                    'instructions' => "Taie legumele. Înmoaie ceapa și morcovul în ulei, apoi adaugă varza, cartofii și apa.\n\nFierbe la foc mic până când legumele sunt fragede. Adaugă roșiile și mai fierbe 10 minute.\n\nPotrivește gustul cu sare, piper și zeamă de lămâie. Adaugă mărarul înainte de servire."],
                ['title' => 'Plăcintă cu mere', 'category' => 'dessert', 'duration_minutes' => 75, 'servings' => 8, 'difficulty' => 'medium', 'photo' => 'pie.png',
                    'ingredients' => ['500 g aluat foietaj', '1 kg mere', '80 g zahăr', '1 linguriță scorțișoară', '2 linguri griș', '1 ou pentru uns', 'Zahăr pudră, opțional'],
                    'instructions' => "Încinge cuptorul la 180°C. Curăță și rade merele, apoi gătește-le cu zahărul și scorțișoara până scade lichidul. Lasă umplutura să se răcească.\n\nAșază jumătate din aluat într-o tavă, presară grișul și întinde merele. Acoperă cu restul de aluat și crestează ușor suprafața.\n\nUnge cu ou și coace aproximativ 35–40 de minute, până se rumenește. Lasă plăcinta să se răcească înainte de porționare."],
            ];
            foreach ($samples as $sample) {
                $photo = $sample['photo'];
                unset($sample['photo']);
                $sample['visibility'] = 'family';
                app(RecipeCatalog::class)->create(DemoSeeder::POPESCU, $owner, $sample,
                    new UploadedFile(database_path('seeders/assets/kitchen/'.$photo), $photo, 'image/png', null, true), true);
            }
        });
    }
}
