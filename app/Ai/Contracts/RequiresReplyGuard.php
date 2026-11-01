<?php

declare(strict_types=1);

namespace App\Ai\Contracts;

/**
 * Marker interface for agents whose replies must pass EchoReplyGuard before
 * they are streamed or persisted.
 */
interface RequiresReplyGuard {}
