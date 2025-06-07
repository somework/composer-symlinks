<?php
declare(strict_types=1);

namespace SomeWork\Symlinks;

class Symlink
{
    protected string $target = '';
    protected string $link = '';
    protected bool $absolutePath = false;
    protected bool $forceCreate = false;

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setTarget(string $target): Symlink
    {
        $this->target = $target;
        return $this;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): Symlink
    {
        $this->link = $link;
        return $this;
    }

    public function isAbsolutePath(): bool
    {
        return $this->absolutePath;
    }

    public function setAbsolutePath(bool $absolutePath): Symlink
    {
        $this->absolutePath = $absolutePath;
        return $this;
    }

    public function isForceCreate(): bool
    {
        return $this->forceCreate;
    }

    public function setForceCreate(bool $forceCreate): Symlink
    {
        $this->forceCreate = $forceCreate;
        return $this;
    }
}
