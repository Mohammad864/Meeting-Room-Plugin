<?php

namespace MRB\Core;

use MRB\Contracts\NotificationServiceInterface;
use MRB\Contracts\ReservationRepositoryInterface;
use MRB\Controllers\Admin\CalendarController;
use MRB\Controllers\Admin\ReservationActionController;
use MRB\Controllers\Admin\ReservationController;
use MRB\Controllers\Admin\SettingsController;
use MRB\Controllers\Front\BookingController;
use MRB\Controllers\Front\GuestReservationController;
use MRB\Controllers\Front\ManageReservationController;
use MRB\Repositories\ReservationRepository;
use MRB\Repositories\RoomRepository;
use MRB\Services\ConflictDetector;
use MRB\Services\EmailNotificationService;
use MRB\Services\MinimumRoomsCalculator;
use MRB\Services\ReservationService;
use MRB\Services\RoomAllocator;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Simple dependency-injection container.
 *
 * Every binding is a singleton — resolved at most once per request.
 * Call Container::build() to get a fully-wired instance.
 */
final class Container
{
    /** @var array<string, callable> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * @throws \RuntimeException When no binding exists for $abstract.
     */
    public function make(string $abstract): object
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            throw new \RuntimeException(
                sprintf(
                    "[MRB Container] No binding registered for [%s].",
                    $abstract,
                ),
            );
        }

        $this->instances[$abstract] = $this->bindings[$abstract]($this);

        return $this->instances[$abstract];
    }


    /**
     * Alias for make() — resolves a binding by its abstract class/interface name.
     *
     * @throws \RuntimeException When no binding exists for $abstract.
     */
    public function get(string $abstract): object
    {
        return $this->make($abstract);
    }
    public static function build(): self
    {
        $c = new self();

        // ── Repositories (Model layer – data access) ──────────────────────────
        $c->singleton(
            ReservationRepositoryInterface::class,
            static fn() => new ReservationRepository(),
        );

        $c->singleton(
            RoomRepository::class,
            static fn() => new RoomRepository(),
        );

        // ── Notifications ─────────────────────────────────────────────────────
        $c->singleton(
            NotificationServiceInterface::class,
            static fn() => new EmailNotificationService(),
        );

        // ── Utility services ──────────────────────────────────────────────────
        $c->singleton(
            MinimumRoomsCalculator::class,
            static fn() => new MinimumRoomsCalculator(),
        );

        // ── Room allocation ───────────────────────────────────────────────────
        $c->singleton(
            ConflictDetector::class,
            static fn(self $c) => new ConflictDetector(
                $c->make(ReservationRepositoryInterface::class),
            ),
        );

        $c->singleton(
            RoomAllocator::class,
            static fn(self $c) => new RoomAllocator(
                $c->make(RoomRepository::class),
                $c->make(ConflictDetector::class),
            ),
        );

        // ── Core service ──────────────────────────────────────────────────────
        $c->singleton(
            ReservationService::class,
            static fn(self $c) => new ReservationService(
                $c->make(ReservationRepositoryInterface::class),
                $c->make(NotificationServiceInterface::class),
                $c->make(RoomAllocator::class),
            ),
        );

        // ── Admin controllers ─────────────────────────────────────────────────
        $c->singleton(
            ReservationController::class,
            static fn(self $c) => new ReservationController(
                $c->make(ReservationRepositoryInterface::class),
                $c->make(MinimumRoomsCalculator::class),
            ),
        );

        $c->singleton(
            ReservationActionController::class,
            static fn(self $c) => new ReservationActionController(
                $c->make(ReservationService::class),
            ),
        );

        $c->singleton(
            CalendarController::class,
            static fn(self $c) => new CalendarController(
                $c->make(ReservationRepositoryInterface::class),
            ),
        );

        $c->singleton(
            SettingsController::class,
            static fn() => new SettingsController(),
        );

        // ── Front controllers ─────────────────────────────────────────────────
        $c->singleton(
            BookingController::class,
            static fn(self $c) => new BookingController(
                $c->make(ReservationService::class),
            ),
        );

        $c->singleton(
            GuestReservationController::class,
            static fn(self $c) => new GuestReservationController(
                $c->make(ReservationService::class),
            ),
        );

        $c->singleton(
            ManageReservationController::class,
            static fn(self $c) => new ManageReservationController(
                $c->make(ReservationRepositoryInterface::class),
            ),
        );

        return $c;
    }
}
