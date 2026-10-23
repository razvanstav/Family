<?php

namespace Tests\Feature;

use App\Modules\Calendar\Contracts\Overview;
use App\Modules\Documents\Contracts\DocumentSummaries;
use App\Modules\Home\Http\HomeController;
use App\Modules\Memory\Contracts\MomentSummaries;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

final class ModuleContractsTest extends TestCase
{
    public function test_home_can_compose_authorized_module_contracts_without_their_implementations(): void
    {
        $documents = Mockery::mock(DocumentSummaries::class);
        $moments = Mockery::mock(MomentSummaries::class);
        $calendar = Mockery::mock(Overview::class);
        $documents->shouldReceive('recent')->once()->with('family', 17, 'membership')->andReturn(collect([['id' => 'document']]));
        $moments->shouldReceive('forHome')->once()->with('family', 17)->andReturn(collect([['id' => 'moment']]));
        $calendar->shouldReceive('home')->once()->with('family', 17, '2026-10')->andReturn(['events' => [['id' => 'event']], 'reminders' => []]);
        $this->app->instance(DocumentSummaries::class, $documents);
        $this->app->instance(MomentSummaries::class, $moments);
        $this->app->instance(Overview::class, $calendar);
        $request = Request::create('/home', 'GET', ['month' => '2026-10']);
        $request->setUserResolver(fn () => (object) ['id' => 17]);
        $request->attributes->set('membership', (object) ['id' => 'membership']);
        $response = $this->app->call([new HomeController, '__invoke'], ['request' => $request, 'family' => 'family']);

        $this->assertSame(['documents' => [['id' => 'document']], 'memories' => [['id' => 'moment']],
            'events' => [['id' => 'event']], 'reminders' => []], $response->getData(true));
    }
}
