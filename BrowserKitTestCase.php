<?php

use Tests\Concerns\LoginAsUsers;
use Tests\Concerns\MockServices;

/**
 * Class BrowserKitTestCase
 */
abstract class BrowserKitTestCase extends Laravel\BrowserKitTesting\TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration, MockServices, LoginAsUsers;

    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        \Illuminate\Support\Facades\Hash::setRounds(4);

        return $app;
    }

    /**
     * @param  mixed  $expected
     * @param  string  $key
     */
    public function assertInResponseAt( $expected, string $key): void
    {
        $jsonDecode = json_decode($this->response->getContent(), true);
        $this->assertEquals(Arr::get($jsonDecode, $key), $expected, 'Could not find '.json_encode($expected).' at '.$key.'. Found '.json_encode(Arr::get($jsonDecode, $key)));
    }

    protected function deleteHistorical()
    {
        \DB::delete('delete from vw_chain_snapshot_listing');
        \DB::delete('delete from vw_proxy_branch_listings');
        \DB::delete('delete from vw_chain_snapshots');
        \DB::delete('delete from vw_oauth_refresh_tokens');
        \DB::delete('delete from vw_oauth_access_tokens');
        \DB::delete('delete from vw_usage_log');
        \DB::delete('delete from vw_milestone_checklist_items');
        \DB::delete('delete from vw_milestone_checklists');
        \DB::delete('delete from vw_branch_milestone_type');
        \DB::delete('delete from vw_contact_listing');
        \DB::delete('delete from vw_listing_meta');
        \DB::delete('delete from vw_contact_report');
        \DB::delete('delete from vw_chains');
        \DB::delete('delete from vw_changes');
        \DB::delete('delete from vw_milestones');
        \DB::delete('delete from vw_listings');
        \DB::delete('delete from vw_role_user where user_id != -999');
        \DB::delete('delete from vw_team_users where user_id != -999');
        \DB::delete('delete from vw_announcements where user_id != -999');
        \DB::delete('delete from vw_notifications where user_id != -999');
        \DB::delete('delete from vw_users where id != -999');
        \DB::delete('delete from vw_teams');
        \DB::delete('delete from vw_group_subscriptions');
        \DB::delete('delete from consumer.vw_transactions');
        \DB::delete('delete from vw_branches');
        \DB::delete('delete from vw_brands');
        \DB::delete('delete from vw_corporations');
        \DB::delete('delete from vw_addresses');
        \DB::delete('delete from vw_experian_returns');
        \DB::delete('delete from vw_contacts');
        \DB::delete('delete from vw_notes');
        \DB::delete('delete from vw_chain_transactions');
        \DB::delete('delete from vw_milestone_search_expected_lags where id != 1');
    }

    /**
     *
     */
    protected function tearDown(): void
    {
        Mockery::close();

        // $this->deleteHistorical();

        parent::tearDown();
    }

    /**
     * @param       $count
     * @param  array  $listingAttributes
     *
     * @param  int  $listingCount
     *
     * @return \Illuminate\Support\Collection
     * @throws \App\Exceptions\ChainLinkException
     */
    protected function createChains($count, $listingAttributes = [], $listingCount = 2)
    {
        $chains = collect();
        for ($i = 0; $i < $count; $i++) {
            $chains = $chains->push($this->createChain([], $listingCount, $listingAttributes));
        }

        return $chains;
    }

    /**
     * @param  array  $listings
     * @param  int  $count
     * @param  array  $listingAttributes
     *
     * @return mixed
     */
    protected function createChain($listings = [], $count = 10, $listingAttributes = [])
    {
        if (!auth()->user()) {
            $userType = \App\Models\UserType::where('name', \App\Models\UserType::ADMIN)->first();
            $user = property_exists($this, 'user') ? $this->user : factory(\App\Models\User::class)->create(['user_type_id' => $userType->id]);

            $user->roles()->sync([\App\Models\Role::whereName(\App\Models\Role::SUPER_ADMIN)->first()->id]);

            $this->be($user);
        }

        if (is_array($listings)) {
            $listings = collect($listings);
        }

        if ($listings->count() == 0) {
            $listings = factory(\App\Models\Listing::class, $count)->create($listingAttributes);
        }

        for ($i = 0; $i < $listings->count() - 1; $i++) {
            $link = app('ChainRepository')->link($listings->get($i)->fresh(), $listings->get($i + 1)->fresh());
        }

        return $listings->first()->fresh()->chain;
    }

    /**
     * @param $user
     *
     * @return \App\Models\Team
     */
    protected function addValidSubscription($user = null)
    {
        $team = $this->createTeam($user);

        $subscription = factory(\App\Models\GroupSubscription::class)->create(['start_date' => now()->subDay()]);

        return tap($team)->update(['group_subscription_id' => $subscription->id]);
    }

    /**
     * @param  null  $user
     * @param  string  $role
     *
     * @return \App\Models\Team
     */
    protected function createTeam($user = null, $role = 'owner')
    {
        $user = $user ?? \App\Models\User::withoutSyncingToSearch(function () {
                return factory(\App\Models\User::class)->create();
            });

        $team = (new \App\Models\Team)->forceFill([
            'name'      => 'New Team',
            'owner_id'  => $user->id,
            'branch_id' => \App\Models\Branch::withoutSyncingToSearch(function () {
                return factory(\App\Models\Branch::class)->create()->id;
            }),
        ]);

        $team->save();

        $user->teams()->attach($team->id, ['role' => $role]);

        return $team->fresh();
    }

    /**
     *
     */
    protected function dumpContent()
    {
        dump($this->response->content());
    }

    /**
     *
     */
    protected function dumpContentAsArray()
    {
        dump(json_decode($this->response->content(), true));
    }

    protected function createChainsForConsumer()
    {
        $user = auth()->user() ?: $this->signInAsConsumer();
        //create sale properties for consumer
        $saleRoleId = app('ListingRoleRepository')->getSellerRole()->id;
        $purchaseRoleId = app('ListingRoleRepository')->getBuyerRole()->id;

        $listings = factory(\App\Models\Listing::class, 2)->create();
        foreach ($listings as $k => $listing) {
            $pairListing = factory(\App\Models\Listing::class, 1)->create()->first();
            app('ChainRepository')->link($listing, $pairListing);
            $user->contact
                ->listings()
                ->save($listing, ['listing_role_id' => $saleRoleId]);
            $user->contact
                ->listings()
                ->save($pairListing, ['listing_role_id' => $purchaseRoleId]);
        }

        //create purchase properties for consumer
        $listings = factory(\App\Models\Listing::class, 2)->create();
        foreach ($listings as $k => $listing) {
            $pairListing = factory(\App\Models\Listing::class, 1)->create()->first();
            app('ChainRepository')->link($pairListing, $listing);
            $user->contact
                ->listings()
                ->save($listing, ['listing_role_id' => $purchaseRoleId]);
            $user->contact
                ->listings()
                ->save($pairListing, ['listing_role_id' => $saleRoleId]);
        }
    }

    protected function dumper($value)
    {
        dump($value);
    }
}
