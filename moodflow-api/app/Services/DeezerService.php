<?php

namespace App\Services;

use App\DTOs\Track;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeezerService
{
    public function __construct(private readonly PendingRequest $http) {}

    /**
     * Search tracks by query string.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchTracks(string $query, int $limit = 25): array
    {
        $key = 'deezer:search:' . md5($query) . ':' . $limit;

        return Cache::remember($key, 3600, function () use ($query, $limit): array {
            $data = $this->request('/search', ['q' => $query, 'limit' => $limit]);

            return collect($data['data'] ?? [])
                ->map(fn(array $raw) => $this->normalizeTrack($raw)->toArray())
                ->filter(fn(array $track) => $track['preview_url'] !== '')
                ->values()
                ->all();
        });
    }

    /**
     * Get radios/playlists for a given Deezer genre ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPlaylistsByGenre(int $genreId): array
    {
        return Cache::remember("deezer:genre:{$genreId}:radios", 3600, function () use ($genreId): array {
            $data = $this->request("/genre/{$genreId}/radios");

            return collect($data['data'] ?? [])
                ->map(fn(array $raw) => [
                    'deezer_id'    => (int) $raw['id'],
                    'title'        => $raw['title'] ?? '',
                    'picture_url'  => $raw['picture_medium'] ?? '',
                    'tracklist_url' => $raw['tracklist'] ?? '',
                ])
                ->all();
        });
    }

    /**
     * Get Deezer editorial top playlists (all genres).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEditorialPlaylists(): array
    {
        return Cache::remember('deezer:editorial:playlists', 3600, function (): array {
            $data = $this->request('/chart/0/playlists');

            return collect($data['data'] ?? [])
                ->map(fn(array $raw) => [
                    'deezer_id'    => (int) $raw['id'],
                    'title'        => $raw['title'] ?? '',
                    'picture_url'  => $raw['picture_medium'] ?? '',
                    'nb_tracks'    => (int) ($raw['nb_tracks'] ?? 0),
                    'tracklist_url' => $raw['tracklist'] ?? '',
                ])
                ->all();
        });
    }

    /**
     * Perform a GET request to the Deezer API.
     *
     * Returns an empty array on timeout or non-2xx response.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $path, array $query = []): array
    {
        try {
            $response = $this->http->get($path, $query);

            if (! $response->successful()) {
                Log::warning('Deezer HTTP error', [
                    'path'   => $path,
                    'status' => $response->status(),
                ]);

                return [];
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            Log::warning('Deezer connection error', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function normalizeTrack(array $raw): Track
    {
        return new Track(
            deezerId:   (int) ($raw['id'] ?? 0),
            title:      $raw['title'] ?? '',
            artist:     $raw['artist']['name'] ?? '',
            album:      $raw['album']['title'] ?? '',
            coverUrl:   $raw['album']['cover_medium'] ?? '',
            previewUrl: $raw['preview'] ?? '',
            deezerUrl:  $raw['link'] ?? '',
        );
    }
}
