<?php

namespace App\DTOs;

readonly class Track
{
    public function __construct(
        public int $deezerId,
        public string $title,
        public string $artist,
        public string $album,
        public string $coverUrl,
        public string $previewUrl,
        public string $deezerUrl,
    ) {}

    public function toArray(): array
    {
        return [
            'deezer_id'   => $this->deezerId,
            'title'       => $this->title,
            'artist'      => $this->artist,
            'album'       => $this->album,
            'cover_url'   => $this->coverUrl,
            'preview_url' => $this->previewUrl,
            'deezer_url'  => $this->deezerUrl,
        ];
    }
}
