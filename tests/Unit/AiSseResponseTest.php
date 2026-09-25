<?php

use App\Http\Responses\AiSseResponse;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Stringable;

beforeEach(function () {
    $this->originalContainer = Container::getInstance();
    $this->originalExecutionTime = (int) ini_get('max_execution_time');

    $container = new Container;
    $container->instance(ResponseFactoryContract::class, new ResponseFactory(
        Mockery::mock(ViewFactory::class),
        Mockery::mock(Redirector::class),
    ));
    Container::setInstance($container);
});

afterEach(function () {
    set_time_limit($this->originalExecutionTime);
    Container::setInstance($this->originalContainer);
    Mockery::close();
});

test('stream execution budget applies during lazy iteration and restores afterward', function (int $originalLimit, int $streamLimit) {
    set_time_limit($originalLimit);
    $observedLimits = [];
    $events = (function () use (&$observedLimits): Generator {
        $observedLimits[] = (int) ini_get('max_execution_time');
        yield new Stringable('first');

        $observedLimits[] = (int) ini_get('max_execution_time');
        yield new Stringable('second');

        $observedLimits[] = (int) ini_get('max_execution_time');
    })();

    $response = AiSseResponse::from($events);

    expect((int) ini_get('max_execution_time'))->toBe($originalLimit)
        ->and($observedLimits)->toBe([])
        ->and($response->headers->get('Content-Type'))->toBe('text/event-stream');

    ob_start();
    ob_start();

    try {
        $response->sendContent();
        ob_end_flush();
        $content = ob_get_contents();
    } finally {
        ob_end_clean();
    }

    expect($observedLimits)->toBe([$streamLimit, $streamLimit, $streamLimit])
        ->and((int) ini_get('max_execution_time'))->toBe($originalLimit)
        ->and($content)->toBe("data: first\n\ndata: second\n\ndata: [DONE]\n\n");
})->with([
    'default limit' => [30, 180],
    'below budget' => [179, 180],
    'equal budget' => [180, 180],
    'higher limit' => [300, 300],
    'unlimited' => [0, 0],
]);

test('stream execution budget restores when iteration throws', function (bool $afterEvent, string $exceptionClass) {
    set_time_limit(30);
    $failure = new $exceptionClass('Stream failed');
    $observedLimit = null;
    $events = (function () use ($afterEvent, $failure, &$observedLimit): Generator {
        if ($afterEvent) {
            yield new Stringable('first');
        }

        $observedLimit = (int) ini_get('max_execution_time');

        throw $failure;
    })();
    $response = AiSseResponse::from($events);
    $caught = null;
    $bufferLevel = ob_get_level();
    ob_start();
    ob_start();

    try {
        try {
            $response->sendContent();
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        ob_end_flush();
        $content = ob_get_contents();
    } finally {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
    }

    expect($observedLimit)->toBe(180)
        ->and((int) ini_get('max_execution_time'))->toBe(30)
        ->and($caught)->toBe($failure)
        ->and($content)->toBe($afterEvent ? "data: first\n\n" : '');
})->with([false, true])->with([RuntimeException::class, Error::class]);
