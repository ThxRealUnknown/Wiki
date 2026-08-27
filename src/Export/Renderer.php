<?php

namespace App\Export;

/**
 * One output format. Implementations turn a Document into the bytes of a file.
 */
interface Renderer
{
    /** File extension, without the dot. */
    public function extension(): string;

    /** Value for the Content-Type header. */
    public function contentType(): string;

    public function render(Document $document): string;
}
