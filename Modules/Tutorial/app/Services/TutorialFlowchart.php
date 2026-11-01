<?php

namespace Modules\Tutorial\Services;

use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\Mission;
use Modules\Tutorial\Scenarios\Scenario;

/**
 * Lays out a scenario as a swimlane flowchart for «نمای فلوچارت»: one lane
 * per actor (in order of first appearance), the missions top to bottom in
 * their order, a band per chapter, and connectors from each mission to the
 * next. Positions are worked out here so the view only draws them.
 */
class TutorialFlowchart
{
    public const HEADER_HEIGHT = 48;

    public const CHAPTER_HEIGHT = 36;

    public const ROW_HEIGHT = 84;

    public const NODE_HEIGHT = 60;

    /**
     * @return array{
     *     lanes: list<string>,
     *     height: int,
     *     chapters: list<array{title: string, top: int}>,
     *     nodes: list<array{number: int, title: string, actor: string, lane: int, top: int, center: float, state: string, otherAccount: bool}>,
     *     edges: list<array{from: array{x: float, y: int}, to: array{x: float, y: int}, middle: int}>
     * }
     */
    public function layout(Scenario $scenario, ?TutorialRun $run = null, ?Mission $current = null): array
    {
        $lanes = [];
        $chapters = [];
        $nodes = [];
        $top = self::HEADER_HEIGHT;
        $number = 0;

        foreach ($scenario->chapters() as $chapter => $missions) {
            $chapters[] = ['title' => $chapter, 'top' => $top];
            $top += self::CHAPTER_HEIGHT;

            foreach ($missions as $mission) {
                $actor = $mission->actorLabel();
                $lane = array_search($actor, $lanes, true);

                if ($lane === false) {
                    $lanes[] = $actor;
                    $lane = count($lanes) - 1;
                }

                $nodes[] = [
                    'number' => ++$number,
                    'title' => $mission->title,
                    'actor' => $actor,
                    'lane' => $lane,
                    'top' => $top + intdiv(self::ROW_HEIGHT - self::NODE_HEIGHT, 2),
                    'center' => 0.0,
                    'state' => match (true) {
                        $run === null => 'idle',
                        $run->isCompleted($mission->key) => 'done',
                        $current?->key === $mission->key => 'current',
                        default => 'locked',
                    },
                    'otherAccount' => $mission->needsAnotherAccount(),
                ];
                $top += self::ROW_HEIGHT;
            }
        }

        $laneWidth = 100 / max(count($lanes), 1);

        foreach ($nodes as $index => $node) {
            // RTL: the first lane is on the right.
            $nodes[$index]['center'] = round(100 - ($node['lane'] + 0.5) * $laneWidth, 3);
        }

        $edges = [];

        for ($index = 1; $index < count($nodes); $index++) {
            $from = $nodes[$index - 1];
            $to = $nodes[$index];
            $start = $from['top'] + self::NODE_HEIGHT;

            $edges[] = [
                'from' => ['x' => $from['center'], 'y' => $start],
                'to' => ['x' => $to['center'], 'y' => $to['top']],
                // The turn sits just above the next mission, below any chapter band.
                'middle' => $to['top'] - intdiv(self::ROW_HEIGHT - self::NODE_HEIGHT, 2),
            ];
        }

        return [
            'lanes' => $lanes,
            'height' => $top + 8,
            'chapters' => $chapters,
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
