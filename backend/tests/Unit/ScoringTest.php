<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

final class ScoringTest extends TestCase
{
    public static function levelThresholds(): array
    {
        // score, expected level (umbrales por defecto: ex=90, g=70, w=50, c=30)
        return [
            'excellent boundary' => [90, 'excellent'],
            'just excellent'     => [95, 'excellent'],
            'good boundary'      => [70, 'good'],
            'upper good'         => [89, 'good'],
            'warning boundary'   => [50, 'warning'],
            'upper warning'      => [69, 'warning'],
            'just below warning' => [49, 'critical'],
            'zero'               => [0, 'critical'],
            'hundred'            => [100, 'excellent'],
        ];
    }

    #[Test]
    #[DataProvider('levelThresholds')]
    public function get_level_maps_score_to_expected_bucket(int $score, string $expected): void
    {
        $this->assertSame($expected, Scoring::getLevel($score));
    }

    #[Test]
    public function clamp_respects_0_to_100_range(): void
    {
        $this->assertSame(0, Scoring::clamp(-50));
        $this->assertSame(100, Scoring::clamp(150));
        $this->assertSame(42, Scoring::clamp(42));
    }

    #[Test]
    public function global_score_is_weighted_average(): void
    {
        $modules = [
            ['score' => 80, 'weight' => 0.5],
            ['score' => 60, 'weight' => 0.5],
        ];

        $this->assertSame(70, Scoring::calculateGlobalScore($modules));
    }

    #[Test]
    public function global_score_skips_null_modules(): void
    {
        $modules = [
            ['score' => 80, 'weight' => 0.3],
            ['score' => null, 'weight' => 0.5], // analyzer que falló
            ['score' => 60, 'weight' => 0.2],
        ];
        // Debe ignorar el null y normalizar sobre los pesos restantes
        // (80*0.3 + 60*0.2) / (0.3+0.2) = 36/0.5 = 72
        $this->assertSame(72, Scoring::calculateGlobalScore($modules));
    }

    #[Test]
    public function global_score_returns_zero_when_no_valid_modules(): void
    {
        $this->assertSame(0, Scoring::calculateGlobalScore([]));
        $this->assertSame(0, Scoring::calculateGlobalScore([
            ['score' => null, 'weight' => 0.5],
        ]));
    }

    #[Test]
    public function module_score_simple_average_when_no_weights(): void
    {
        $metrics = [
            ['id' => 'a', 'score' => 80],
            ['id' => 'b', 'score' => 60],
            ['id' => 'c', 'score' => 100],
        ];
        $this->assertSame(80, Scoring::calculateModuleScore($metrics));
    }

    #[Test]
    public function module_score_weighted_when_weights_provided(): void
    {
        $metrics = [
            ['id' => 'ssl', 'score' => 100],
            ['id' => 'headers', 'score' => 40],
        ];
        $weights = ['ssl' => 3, 'headers' => 1];
        // (100*3 + 40*1) / 4 = 340/4 = 85
        $this->assertSame(85, Scoring::calculateModuleScore($metrics, $weights));
    }

    #[Test]
    public function count_issues_groups_by_level(): void
    {
        $modules = [
            ['metrics' => [
                ['level' => 'critical'],
                ['level' => 'critical'],
                ['level' => 'warning'],
                ['level' => 'good'],
                ['level' => 'excellent'],
                ['level' => 'info'],
            ]],
        ];

        $this->assertSame(
            ['critical' => 2, 'warning' => 1, 'good' => 2],
            Scoring::countIssues($modules)
        );
    }

    #[Test]
    public function solution_map_includes_only_critical_and_warning(): void
    {
        $modules = [
            ['metrics' => [
                ['name' => 'SSL', 'description' => 'inválido', 'level' => 'critical', 'imaginaSolution' => 'cert Lets Encrypt'],
                ['name' => 'Cache', 'description' => 'falta', 'level' => 'warning', 'imaginaSolution' => 'Cloudflare'],
                ['name' => 'H1', 'description' => 'ok', 'level' => 'good', 'imaginaSolution' => ''],
            ]],
        ];

        $map = Scoring::generateSolutionMap($modules);

        $this->assertCount(2, $map);
        $this->assertSame('Basic', $map[0]['includedInPlan']); // critical → Basic
        $this->assertSame('Pro', $map[1]['includedInPlan']);   // warning → Pro
    }

    #[Test]
    public function create_metric_clamps_score_and_derives_level(): void
    {
        $m = Scoring::createMetric('id', 'Name', 'val', 'disp', 150, 'd', 'r', 's');
        $this->assertSame(100, $m['score']);
        $this->assertSame('excellent', $m['level']);

        $m2 = Scoring::createMetric('id', 'Name', 'val', 'disp', null, 'd', 'r', 's');
        $this->assertNull($m2['score']);
        $this->assertSame('info', $m2['level']);
    }
}
