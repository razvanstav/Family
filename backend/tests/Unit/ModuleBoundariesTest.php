<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ModuleBoundariesTest extends TestCase
{
    public function test_modules_and_platform_only_consume_public_module_contracts_and_owned_tables(): void
    {
        $root = dirname(__DIR__, 2).'/app';
        $violations = [];
        foreach (['Modules', 'Platform'] as $area) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$area, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                foreach ($this->violations($path, file_get_contents($file->getPathname())) as $violation) {
                    $violations[] = $path.': '.$violation;
                }
            }
        }
        $this->assertSame([], $violations);
    }

    public function test_guard_detects_imports_fully_qualified_names_and_class_strings(): void
    {
        foreach ([
            '<?php use App\\Modules\\Kitchen\\Application\\MealCatalog;',
            '<?php new \\App\\Modules\\Kitchen\\Application\\MealCatalog;',
            '<?php app("App\\\\Modules\\\\Kitchen\\\\Application\\\\MealCatalog");',
        ] as $source) {
            $this->assertNotEmpty($this->violations('Modules/Memory/Application/Reader.php', $source));
            $this->assertNotEmpty($this->violations('Platform/Families/Reader.php', $source));
        }
        $this->assertSame([], $this->violations('Modules/Memory/Application/Reader.php', '<?php use App\\Modules\\Kitchen\\Contracts\\Activity;'));
        $this->assertSame([], $this->violations('Modules/Memory/Application/Reader.php', '<?php use App\\Modules\\Memory\\Application\\MomentAccess;'));
        $this->assertSame([], $this->violations('Modules/Memory/Application/Reader.php', '<?php use App\\Platform\\Contracts\\Outbox;'));
        $this->assertNotEmpty($this->violations('Modules/Kitchen/Contracts/Meals.php', '<?php use App\\Modules\\Kitchen\\Application\\MealCatalog;'));
    }

    public function test_guard_rejects_foreign_query_tables_and_raw_sql_but_allows_platform_tables(): void
    {
        foreach ([
            '<?php DB::table("document_grants")->delete();',
            '<?php $query->join("documents as d", "d.id", "=", "l.id");',
            '<?php DB::statement("UPDATE documents SET title = ?");',
            '<?php DB::select("SELECT * FROM calendar_items");',
            '<?php DB::table("expenses")->get();',
            '<?php DB::table("family_tasks")->get();',
        ] as $source) {
            $this->assertNotEmpty($this->violations('Modules/Vehicles/Http/Controller.php', $source));
            $this->assertNotEmpty($this->violations('Platform/Families/Controller.php', $source));
        }
        $this->assertSame([], $this->violations('Modules/Vehicles/Http/Controller.php', '<?php DB::table("vehicle_documents as l"); DB::table("family_memberships");'));
        $this->assertSame([], $this->violations('Modules/Vehicles/Http/Controller.php', '<?php $payload = ["documents" => [], "source_type" => "vehicle_entry", "document_id" => "id"];'));
    }

    /** @return list<string> */
    private function violations(string $path, string $source): array
    {
        $parts = explode('/', $path);
        $owner = $parts[0] === 'Modules' ? $parts[1] : null;
        $isContract = str_contains($path, '/Contracts/');
        $violations = [];
        $tokens = token_get_all($source);
        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }
            if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_CONSTANT_ENCAPSED_STRING], true)) {
                $name = ltrim(str_replace('\\\\', '\\', trim($token[1], '\'"')), '\\');
                if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)\\\\([^\\\\]+)/', $name, $target)
                    && ($target[1] !== $owner || $isContract) && $target[2] !== 'Contracts') {
                    $violations[] = 'Private dependency '.$name;
                }
            }
            if ($token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $value = trim($token[1], '\'"');
            $tables = $this->isTableArgument($tokens, $index) && preg_match('/^[a-z_]+(?:\s+(?:as\s+)?[a-z_]+)?$/i', $value)
                ? [explode(' ', $value)[0]] : [];
            preg_match_all('/\b(?:FROM|JOIN|UPDATE|INTO)\s+([a-z_]+)/i', $value, $sql);
            foreach (array_unique(array_merge($tables, $sql[1])) as $table) {
                $tableOwner = $this->tableOwner(strtolower($table));
                if ($tableOwner !== null && $tableOwner !== $owner) {
                    $violations[] = 'Foreign persistence '.$table;
                }
            }
        }

        return $violations;
    }

    private function isTableArgument(array $tokens, int $index): bool
    {
        $before = $index - 1;
        while ($before >= 0 && is_array($tokens[$before]) && in_array($tokens[$before][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $before--;
        }
        if ($before < 1 || $tokens[$before] !== '(') {
            return false;
        }
        $before--;
        while ($before >= 0 && is_array($tokens[$before]) && $tokens[$before][0] === T_WHITESPACE) {
            $before--;
        }

        return $before >= 0 && is_array($tokens[$before]) && $tokens[$before][0] === T_STRING
            && in_array(strtolower($tokens[$before][1]), ['table', 'from', 'join', 'leftjoin', 'rightjoin', 'crossjoin'], true);
    }

    private function tableOwner(string $table): ?string
    {
        foreach (['Documents' => ['documents', 'document_'], 'Kitchen' => ['recipes', 'recipe_', 'meals', 'meal_', 'kitchen_'],
            'Memory' => ['memory_'], 'Calendar' => ['calendar_', 'birthday_reminder_'], 'Vehicles' => ['vehicles', 'vehicle_'], 'Expenses' => ['expenses'], 'Tasks' => ['family_tasks']] as $owner => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_ends_with($prefix, '_') ? str_starts_with($table, $prefix) : $table === $prefix) {
                    return $owner;
                }
            }
        }

        return null;
    }
}
