<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class DemoSeeder extends Seeder
{
    public const POPESCU = '11111111-1111-4111-8111-111111111111';

    public const IONESCU = '22222222-2222-4222-8222-222222222222';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('memoria.demo_enabled')) {
            throw new \RuntimeException('Datele demonstrative sunt permise doar local/testing cu MEMORIA_DEMO=true.');
        }
        if (DB::table('families')->exists()) {
            return;
        }
        DB::transaction(function () {
            $ana = User::create(['name' => 'Ana Popescu', 'email' => 'ana@example.test', 'password' => Hash::make('Familie-Demo-2026!')]);
            $mihai = User::create(['name' => 'Mihai Popescu', 'email' => 'mihai@example.test', 'password' => Hash::make('Familie-Demo-2026!')]);
            $elena = User::create(['name' => 'Elena Ionescu', 'email' => 'elena@example.test', 'password' => Hash::make('Familie-Demo-2026!')]);
            foreach ([self::POPESCU => 'Familia Popescu', self::IONESCU => 'Familia Ionescu'] as $id => $name) {
                DB::table('families')->insert(['id' => $id, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ([[self::POPESCU, $ana->id, 'owner'], [self::POPESCU, $mihai->id, 'adult'],
                [self::IONESCU, $ana->id, 'adult'], [self::IONESCU, $elena->id, 'owner']] as [$family, $user, $role]) {
                DB::table('family_memberships')->insert(['id' => (string) Str::uuid(), 'family_id' => $family, 'user_id' => $user, 'role' => $role]);
            }
            foreach ([
                [self::POPESCU, $ana->id, 'Povestea casei noastre', 'home', 'family'],
                [self::POPESCU, $ana->id, 'Jurnal de călătorie', 'memories', 'private'],
                [self::POPESCU, $ana->id, 'Dosarul meu personal', 'personal', 'private'],
                [self::POPESCU, $ana->id, 'Ghid de prim ajutor', 'health', 'family'],
                [self::POPESCU, $mihai->id, 'Documentul privat al lui Mihai', 'personal', 'private'],
                [self::IONESCU, $elena->id, 'Amintiri din casa bunicilor', 'memories', 'family'],
                [self::IONESCU, $elena->id, 'Documentul privat al Elenei', 'personal', 'private'],
            ] as [$family, $owner, $title, $category, $visibility]) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::select("SELECT set_config('app.family_id', ?, true)", [$family]);
                }
                $asset = (string) Str::uuid();
                // Trusted, application-generated fixture; never used for uploaded user content.
                $bytes = self::samplePdf();
                $key = $family.'/'.$asset;
                Storage::disk('local')->put($key, $bytes);
                DB::table('media_assets')->insert([
                    'id' => $asset, 'family_id' => $family, 'created_by' => $owner, 'storage_key' => $key,
                    'original_name' => 'exemplu.pdf', 'mime' => 'application/pdf', 'bytes' => strlen($bytes),
                    'sha256' => hash('sha256', $bytes), 'status' => 'ready', 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('documents')->insert([
                    'id' => (string) Str::uuid(), 'family_id' => $family, 'owner_id' => $owner, 'asset_id' => $asset,
                    'title' => $title, 'category' => $category,
                    'visibility' => match ($category) {
                        'home' => 'adults', 'memories' => 'family', default => $visibility
                    },
                    'subject_membership_id' => in_array($category, ['personal', 'health'], true)
                        ? DB::table('family_memberships')->where('family_id', $family)->where('user_id', $owner)->value('id') : null,
                    'version' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public static function samplePdf(): string
    {
        $stream = 'BT /F1 20 Tf 60 750 Td (Memoria Familiei) Tj 0 -35 Td /F1 11 Tf (Document demonstrativ. Nu contine date personale reale.) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
