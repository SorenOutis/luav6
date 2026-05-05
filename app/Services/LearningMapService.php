<?php

namespace App\Services;

use App\Models\LearningMap\MapNode;
use App\Models\LearningMap\MapNodeRequirement;
use App\Models\LearningMap\MapWorld;
use App\Models\LearningMap\UserMapNodeProgress;
use App\Models\User;
use App\Support\LevelCurve;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LearningMapService
{
    /**
     * Build the full Inertia payload for the /maps page.
     */
    public function payloadForUser(User $user): array
    {
        $worlds = MapWorld::with(['nodes.requirements', 'nodes.rewardBadge'])
            ->orderBy('sort_order')
            ->get();

        $completedSlugs = UserMapNodeProgress::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->join('map_nodes', 'map_nodes.id', '=', 'user_map_node_progress.map_node_id')
            ->pluck('map_nodes.slug')
            ->all();

        $badgeIds = $user->badges()->pluck('badges.id')->map(fn ($id) => (int) $id)->all();

        $xp = (int) $user->exp;
        $level = (int) $user->level;
        $currentLevelFloor = LevelCurve::xpForLevel($level);
        $nextLevelFloor = LevelCurve::xpForLevel($level + 1);

        $serializedWorlds = $worlds->map(fn (MapWorld $w) => $this->serializeWorld($w))->values();

        $completedCollection = collect($completedSlugs);
        $badgeCollection = collect($badgeIds);
        $nextNodeSlug = $this->suggestNextNodeSlug($serializedWorlds, $user, $completedCollection, $badgeCollection);

        $player = [
            'xp' => $xp,
            'level' => $level,
            'points' => (int) ($user->points ?? 0),
            'streakDays' => (int) ($user->current_streak ?? 0),
            'completedNodeSlugs' => $completedSlugs,
            'badgeIds' => $badgeIds,
            'xpIntoLevel' => max(0, $xp - $currentLevelFloor),
            'xpForNextLevel' => max(1, $nextLevelFloor - $currentLevelFloor),
            'nextNodeSlug' => $nextNodeSlug,
        ];

        return [
            'worlds' => $serializedWorlds,
            'player' => $player,
        ];
    }

    /**
     * Find the most natural "next" node for the learner — the first available
     * (unlocked, uncompleted) node in world/node order.
     */
    protected function suggestNextNodeSlug(
        Collection $serializedWorlds,
        User $user,
        Collection $completedSlugs,
        Collection $badgeIds,
    ): ?string {
        foreach ($serializedWorlds as $w) {
            foreach ($w['nodes'] as $n) {
                if ($completedSlugs->contains($n['id'])) {
                    continue;
                }
                $reqs = $n['requirements'] ?? [];
                if (empty($reqs)) {
                    return $n['id'];
                }
                $allMet = true;
                foreach ($reqs as $r) {
                    switch ($r['kind']) {
                        case 'node':
                            if (! $completedSlugs->contains($r['nodeSlug'])) {
                                $allMet = false;
                            }
                            break;
                        case 'xp':
                            if ((int) $user->exp < (int) ($r['amount'] ?? 0)) {
                                $allMet = false;
                            }
                            break;
                        case 'level':
                            if ((int) $user->level < (int) ($r['level'] ?? 0)) {
                                $allMet = false;
                            }
                            break;
                        case 'badge':
                            if (! $badgeIds->contains((int) ($r['badgeId'] ?? 0))) {
                                $allMet = false;
                            }
                            break;
                        case 'streak':
                            if ((int) ($user->current_streak ?? 0) < (int) ($r['amount'] ?? 0)) {
                                $allMet = false;
                            }
                            break;
                    }
                    if (! $allMet) {
                        break;
                    }
                }
                if ($allMet) {
                    return $n['id'];
                }
            }
        }

        return null;
    }

    protected function serializeWorld(MapWorld $world): array
    {
        return [
            'id' => $world->slug,
            'name' => $world->name,
            'theme' => [
                'primary' => $world->primary_color,
                'accent' => $world->accent_color,
                'background' => $world->background_class,
            ],
            'nodes' => $world->nodes->map(fn (MapNode $n) => $this->serializeNode($n))->values(),
        ];
    }

    protected function serializeNode(MapNode $node): array
    {
        return [
            'id' => $node->slug,
            'title' => $node->title,
            'type' => $node->type,
            'x' => $node->x,
            'y' => $node->y,
            'passScore' => $node->effectivePassScore(),
            'target' => $node->target_type && $node->target_id ? [
                'type' => class_basename($node->target_type),
                'id' => (int) $node->target_id,
            ] : null,
            'rewards' => [
                'xp' => (int) $node->reward_xp,
                'points' => (int) $node->reward_points,
                'badgeId' => $node->reward_badge_id,
            ],
            'requirements' => $node->requirements->map(fn (MapNodeRequirement $r) => [
                'kind' => $r->kind,
                'nodeSlug' => $r->target_node_slug,
                'amount' => $r->amount,
                'level' => $r->level,
                'badgeId' => $r->badge_id,
                'minScore' => $r->min_score,
            ])->values(),
        ];
    }

    /**
     * Evaluate a single requirement against the given player state.
     * Returns [met: bool, progress: float in 0..1, label: string].
     */
    public function evaluateRequirement(MapNodeRequirement $req, User $user, Collection $completedSlugs, Collection $badgeIds): array
    {
        switch ($req->kind) {
            case MapNodeRequirement::KIND_NODE:
                $done = $completedSlugs->contains($req->target_node_slug);

                return [$done, $done ? 1.0 : 0.0, 'Complete node'];

            case MapNodeRequirement::KIND_XP:
                $have = (int) $user->exp;
                $need = max(1, (int) $req->amount);

                return [$have >= $need, min(1.0, $have / $need), "Reach {$need} XP"];

            case MapNodeRequirement::KIND_LEVEL:
                $have = (int) $user->level;
                $need = max(1, (int) $req->level);

                return [$have >= $need, min(1.0, $have / $need), "Reach level {$need}"];

            case MapNodeRequirement::KIND_BADGE:
                $has = $req->badge_id && $badgeIds->contains((int) $req->badge_id);

                return [$has, $has ? 1.0 : 0.0, 'Earn badge'];

            case MapNodeRequirement::KIND_STREAK:
                $have = (int) ($user->current_streak ?? 0);
                $need = max(1, (int) $req->amount);

                return [$have >= $need, min(1.0, $have / $need), "Maintain {$need}-day streak"];
        }

        return [false, 0.0, 'Unknown requirement'];
    }

    /**
     * Mark a node complete for a user, granting XP/points/badge.
     * Idempotent — replays are no-ops.
     */
    public function complete(User $user, MapNode $node, ?int $score = null): UserMapNodeProgress
    {
        return DB::transaction(function () use ($user, $node, $score) {
            /** @var UserMapNodeProgress $progress */
            $progress = UserMapNodeProgress::firstOrNew([
                'user_id' => $user->id,
                'map_node_id' => $node->id,
            ]);

            if ($progress->status === 'completed') {
                return $progress;
            }

            $progress->status = 'completed';
            $progress->score = $score;
            $progress->completed_at = Carbon::now();
            $progress->save();

            if ($node->reward_xp > 0 || $node->reward_points > 0) {
                $user->increment('exp', $node->reward_xp);
                if ($node->reward_points > 0) {
                    $user->increment('points', $node->reward_points);
                }
                $user->level = $this->levelForXp((int) $user->fresh()->exp);
                $user->save();

                $user->recordGamificationHistory(
                    $node->reward_xp,
                    $node->reward_points,
                    'Map Node Completed',
                    "Completed map node: {$node->title}"
                );
            }

            if ($node->reward_badge_id && ! $user->badges()->where('badges.id', $node->reward_badge_id)->exists()) {
                $user->badges()->attach($node->reward_badge_id);
            }

            return $progress;
        });
    }

    /**
     * Delegates to the configurable curve in config/gamification.php.
     */
    public function levelForXp(int $xp): int
    {
        return LevelCurve::levelForXp($xp);
    }
}
