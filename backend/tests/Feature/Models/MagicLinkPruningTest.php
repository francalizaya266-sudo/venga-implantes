<?php

namespace Tests\Feature\Models;

use App\Enums\MagicLinkPurpose;
use App\Models\Account;
use App\Models\MagicLink;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La limpieza diaria (model:prune, programada en routes/console.php) borra los enlaces de WhatsApp y los dispositivos
 * recordados 30 días después de vencer.
 */
class MagicLinkPruningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_daily_cleanup_deletes_links_and_devices_expired_more_than_30_days_ago(): void
    {
        $this->travelTo('2026-11-19 09:00');
        $account = Account::factory()->create();
        $oldLink = $this->linkExpiring($account, now()->subDays(31));
        $recentLink = $this->linkExpiring($account, now()->subDays(10));
        $validLink = $this->linkExpiring($account, now()->addDay());
        $oldDevice = $account->rememberedDevices()->create(['token_hash' => str_repeat('a', 64), 'expires_at' => now()->subDays(31)]);
        $validDevice = $account->rememberedDevices()->create(['token_hash' => str_repeat('b', 64), 'expires_at' => now()->addMonth()]);

        $this->artisan('model:prune')->assertSuccessful();

        $this->assertModelMissing($oldLink);
        $this->assertModelExists($recentLink);
        $this->assertModelExists($validLink);
        $this->assertModelMissing($oldDevice);
        $this->assertModelExists($validDevice);
    }

    private function linkExpiring(Account $account, CarbonInterface $expiresAt): MagicLink
    {
        return $account->magicLinks()->create([
            'purpose' => MagicLinkPurpose::Access,
            'token_hash' => hash('sha256', Str::random(32)),
            'expires_at' => $expiresAt,
        ]);
    }
}
