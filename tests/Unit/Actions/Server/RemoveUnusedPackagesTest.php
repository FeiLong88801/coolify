<?php

use App\Actions\Server\RemoveUnusedPackages;
use App\Models\Server;

afterEach(function () {
    Mockery::close();
});

it('removes unused packages without prompts on apt', function () {
    expect(RemoveUnusedPackages::commands('apt'))->toBe([
        'env DEBIAN_FRONTEND=noninteractive apt-get -y autoremove',
    ]);
});

it('removes old install-only kernels on dnf and lets the second command fail quietly', function () {
    $commands = RemoveUnusedPackages::commands('dnf');

    expect($commands)->toBe([
        'dnf autoremove -y',
        "sh -c 'dnf remove -y --oldinstallonly || true'",
    ])->and($commands[1])->toContain('|| true');
});

it('purges old kernels on zypper', function () {
    expect(RemoveUnusedPackages::commands('zypper'))->toBe([
        'zypper --non-interactive purge-kernels',
    ]);
});

it('returns null for a package manager it cannot handle', function (?string $packageManager) {
    expect(RemoveUnusedPackages::commands($packageManager))->toBeNull()
        ->and(RemoveUnusedPackages::supports($packageManager))->toBeFalse();
})->with([['pacman'], ['apk'], ['brew'], [null]]);

it('reports the package managers it supports', function (string $packageManager) {
    expect(RemoveUnusedPackages::supports($packageManager))->toBeTrue()
        ->and(RemoveUnusedPackages::commands($packageManager))->toBeArray()->not->toBeEmpty();
})->with(['apt', 'dnf', 'zypper']);

it('returns an error instead of running commands on an unsupported operating system', function () {
    $server = Mockery::mock(Server::class);

    expect((new RemoveUnusedPackages)->handle($server, 'pacman'))
        ->toBe(['error' => 'Coolify cannot remove unused packages on this operating system.']);
});
