<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class TalkController extends Controller
{
    /**
     * Conferences in the order the archive presents them, with the short
     * label used by the filter chips and the table.
     */
    private const CONFERENCES = [
        'Laracon US' => 'US',
        'Laracon EU' => 'EU',
        'Laracon Online' => 'Online',
        'Laracon AU' => 'AU',
        'Laracon India' => 'India',
    ];

    public function index(): View
    {
        $talks = $this->talks();

        return view('talks.index', [
            'talks' => $talks,
            'total' => $talks->count(),
            'speakerCount' => $this->speakerCount($talks),
            'years' => $this->years($talks),
            'conferences' => $this->conferences($talks),
        ]);
    }

    /**
     * Every talk in the archive, newest year first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function talks(): Collection
    {
        $path = database_path('data/talks.json');

        if (! is_readable($path)) {
            throw new RuntimeException("Talk archive missing at {$path}.");
        }

        $talks = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return collect($talks)
            ->map(fn (array $talk): array => $talk + [
                'short' => self::CONFERENCES[$talk['conference']] ?? $talk['conference'],
                'slug' => Str::slug($talk['conference']),
            ])
            ->sortBy([
                ['year', 'desc'],
                ['conference', 'asc'],
                ['title', 'asc'],
            ])
            ->values();
    }

    /**
     * Distinct speakers, counting each name in a shared credit separately.
     */
    private function speakerCount(Collection $talks): int
    {
        return $talks
            ->flatMap(fn (array $talk): array => preg_split('/\s*(?:,| and | & )\s*/', $talk['speaker']) ?: [])
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->count();
    }

    /**
     * Talk counts per year, newest first — the data behind the hero ledger.
     *
     * @return Collection<int, array{year: int, count: int}>
     */
    private function years(Collection $talks): Collection
    {
        return $talks
            ->countBy('year')
            ->map(fn (int $count, int $year): array => ['year' => $year, 'count' => $count])
            ->sortByDesc('year')
            ->values();
    }

    /**
     * @return Collection<int, array{label: string, short: string, slug: string, count: int}>
     */
    private function conferences(Collection $talks): Collection
    {
        $counts = $talks->countBy('conference');

        return collect(self::CONFERENCES)
            ->map(fn (string $short, string $label): array => [
                'label' => $label,
                'short' => $short,
                'slug' => Str::slug($label),
                'count' => $counts[$label] ?? 0,
            ])
            ->values();
    }
}
