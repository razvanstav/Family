<?php

namespace App\Modules\Kitchen\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class DiningRatings
{
    private const TABLES = ['meal' => 'kitchen_meal_ratings', 'visit' => 'kitchen_visit_ratings'];

    public function active(string $kind, string $family): Builder
    {
        return DB::table(self::TABLES[$kind].' as ratings')->join('family_memberships as raters', function ($join) {
            $join->on('raters.family_id', '=', 'ratings.family_id')->on('raters.id', '=', 'ratings.membership_id');
        })->where('ratings.family_id', $family)->whereNull('ratings.withdrawn_at')->whereNull('raters.revoked_at');
    }

    public function totals(string $kind, string $family): Builder
    {
        return $this->active($kind, $family)->groupBy('ratings.family_id', 'ratings.'.$kind.'_id')
            ->selectRaw('ratings.family_id, ratings.'.$kind.'_id, ROUND(AVG(ratings.score), 2) AS rating_average, COUNT(*) AS rating_count');
    }

    public function details(string $kind, string $family, string $id, int $page = 1): array
    {
        $rows = $this->active($kind, $family)->where('ratings.'.$kind.'_id', $id)
            ->join('users as author', 'author.id', '=', 'raters.user_id')
            ->orderBy('ratings.created_at')->orderBy('ratings.membership_id')->offset(($page - 1) * 20)->limit(21)
            ->get(['ratings.membership_id', 'author.name as author_name', 'ratings.score', 'ratings.comment']);

        return ['ratings' => $rows->take(20)->all(), 'rating_page' => $page, 'rating_has_more' => $rows->count() > 20];
    }

    public function save(string $kind, string $family, string $member, string $id, array $data, ?string $expected, bool $withdraw = false): void
    {
        $query = DB::table(self::TABLES[$kind])->where('family_id', $family)->where($kind.'_id', $id)->where('membership_id', $member);
        $previous = (clone $query)->lockForUpdate()->first();
        abort_unless($expected, 428, 'Versiunea evaluării este necesară.');
        abort_unless($expected === '"'.($previous->version ?? 0).'"', 412, 'Evaluarea ta s-a schimbat. Reîncarcă înainte de a salva.');
        abort_if($withdraw && (! $previous || $previous->withdrawn_at !== null), 404);
        $values = ['score' => $withdraw ? null : $data['score'], 'comment' => $withdraw ? null : ($data['comment'] ?? null),
            'withdrawn_at' => $withdraw ? now() : null, 'version' => ($previous->version ?? 0) + 1, 'updated_at' => now()];
        if ($previous) {
            $query->update($values);
        } else {
            DB::table(self::TABLES[$kind])->insert($values + ['family_id' => $family, $kind.'_id' => $id, 'membership_id' => $member, 'created_at' => now()]);
        }
    }

    public static function payload(object $row): array
    {
        return ['rating_average' => $row->rating_average === null ? null : (float) $row->rating_average,
            'rating_count' => (int) $row->rating_count,
            'my_rating' => $row->my_rating_version === null ? null : ['score' => $row->my_score === null ? null : (int) $row->my_score,
                'comment' => $row->my_rating_comment, 'version' => (int) $row->my_rating_version]];
    }
}
