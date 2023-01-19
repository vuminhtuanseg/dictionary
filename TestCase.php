<?php

use Illuminate\Support\Str;

abstract class TestCase extends Illuminate\Foundation\Testing\TestCase
{
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
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp() : void
    {
        parent::setUp();
    }

    protected function tearDown() : void
    {
        DB::disconnect();

        parent::tearDown();
    }

    protected function signInAsSuperAdmin()
    {
        $user = factory(\App\Models\User::class)->create();

        $role = \App\Models\Role::where('name', \App\Models\Role::SUPER_ADMIN)->first();

        $user->attachRole($role);

        $this->be($user);

        return $user;
    }

    protected function signInAsAdmin()
    {
        $user = factory(\App\Models\User::class)->create();

        $role = \App\Models\Role::where('name', \App\Models\Role::SUPER_ADMIN)->first();

        $user->attachRole($role);

        $this->be($user);

        return $user;
    }

    protected function createChains($count, $listingAttributes = [])
    {
        $chains = collect();
        for ($i = 0; $i < $count; $i++) {
            $chains = $chains->push($this->createChain([], 10, $listingAttributes));
        }

        return $chains;
    }

    protected function createChain($listings = [], $count = 10, $listingAttributes = [])
    {
        if ( ! auth()->user()) {
            $user = property_exists($this, 'user') ? $this->user : factory(\App\Models\User::class)->create();

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
            app('ChainRepository')->link($listings->get($i), $listings->get($i + 1));
        }

        return $listings->first()->fresh()->chain;
    }

    protected function mockIntercom()
    {
        $intercomMock = Mockery::mock(\Intercom\IntercomClient::class);

        $intercomMock->events = $this->makeIntercomEventMock();

        $intercomMock->users = $this->makeIntercomUserMock();

        $intercomMock->users = $this->makeIntercomCompanyMock();

        $intercomMock->leads = $this->makeIntercomLeadMock();

        $intercomMock->admins = $this->makeIntercomAdminMock();

        app()->instance(\App\Contracts\Intercom::class, new \App\Services\Intercom($intercomMock));
    }

    /**
     * @return \Mockery\MockInterface
     */
    protected function makeIntercomEventMock(): \Mockery\MockInterface
    {
        return Mockery::mock(\Intercom\IntercomEvents::class)
                      ->shouldReceive('create')
                      ->with(Mockery::on(function ($argument) {
                          $metaPass = ! isset($argument['meta']) ?: is_array($argument['meta']) && sizeof($argument['meta']) <= 5;

                          return is_array($argument) &&
                                 isset($argument['event_name']) &&
                                 isset($argument['created_at']) &&
                                 (
                                     isset($argument['user_id']) || isset($argument['id']) || isset($argument['email'])
                                 ) &&
                                 $metaPass;
                      }))
                      ->andReturn(true)
                      ->getMock();
    }

    /**
     * @return \Mockery\MockInterface
     */
    protected function makeIntercomUserMock(): \Mockery\MockInterface
    {
        $userMock = Mockery::mock(\Intercom\IntercomUsers::class)
                           ->shouldReceive('create')
                           ->with(Mockery::on(function ($argument) {
                               return is_array($argument) && (isset($argument['user_id']) || isset($argument['email']));
                           }))
                           ->andReturn($this->userResponse())
                           ->getMock();

        $userMock->shouldReceive('getUsers')->with(Mockery::on(function ($argument) {
            return is_array($argument) && isset($argument['email']);
        }))->andReturn($this->userResponse());

        return $userMock;
    }

    /**
     * @return stdClass
     */
    protected function userResponse(): stdClass
    {
        $response = new stdClass();

        $response->id = Str::random();

        return $response;
    }

    /**
     * @return \Mockery\MockInterface
     */
    protected function makeIntercomCompanyMock(): \Mockery\MockInterface
    {
        $companyMock = Mockery::mock(\Intercom\IntercomUsers::class)
                              ->shouldReceive('create')
                              ->with(Mockery::on(function ($argument) {
                                  return is_array($argument) && (isset($argument['company_id']) || isset($argument['name']));
                              }))
                              ->getMock();

        return $companyMock;
    }

    /**
     * @return \Mockery\MockInterface
     */
    protected function makeIntercomLeadMock(): \Mockery\MockInterface
    {
        return Mockery::mock(\Intercom\IntercomLeads::class)
                      ->shouldReceive('create')
                      ->with(Mockery::on(function ($argument) {
                          return is_array($argument) && isset($argument['email']);
                      }))
                      ->andReturn($this->userResponse())
                      ->getMock();
    }

    /**
     * @return \Mockery\MockInterface
     */
    protected function makeIntercomAdminMock(): \Mockery\MockInterface
    {
        return Mockery::mock(\Intercom\IntercomAdmins::class)
                      ->shouldReceive('getAdmins')
                      ->andReturn($this->adminResponse())
                      ->getMock();
    }

    protected function adminResponse(): stdClass
    {
        $response = new stdClass();

        $response->admins = [];

        $faker = Faker\Factory::create();

        for ($i = 0; $i < 10; $i++) {
            $admin              = new stdClass();
            $admin->email       = $faker->email;
            $admin->id          = rand(1, 1000);
            $response->admins[] = $admin;
        }

        $admin        = new stdClass();
        $admin->email = config('intercom.email ');
        $admin->id    = rand(1, 1000);

        $response->admins[] = $admin;

        return $response;
    }

    protected function createTeam($user = null, $role = 'owner')
    {
        $user = $user ?? factory(\App\Models\User::class)->create();

        $team = (new \App\Models\Team)->forceFill([
            'name'      => 'New Team',
            'owner_id'  => $user->id,
            'branch_id' => factory(\App\Models\Branch::class)->create()->id,
        ]);

        $team->save();

        $user->teams()->attach($team->id, ['role' => $role]);

        return $team->fresh();
    }

    protected function dumpContent()
    {
        (new \Illuminate\Support\Debug\Dumper())->dump($this->response->content());
    }

    protected function dumpContentAsArray()
    {
        (new \Illuminate\Support\Debug\Dumper())->dump(json_decode($this->response->content(), true));
    }
}
