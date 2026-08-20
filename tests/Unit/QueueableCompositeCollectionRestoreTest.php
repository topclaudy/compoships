<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Awobaz\Compoships\Queue\QueueableCompositeCollection;
use Awobaz\Compoships\Tests\Models\SoftDeleteTenantUser;
use Awobaz\Compoships\Tests\Models\TenantUser;
use Awobaz\Compoships\Tests\Models\User;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueueableCompositeCollection::class)]
class QueueableCompositeCollectionRestoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    private function roundTrip(QueueableCompositeCollection $bag): EloquentCollection
    {
        return unserialize(serialize($bag))->restore();
    }

    public function test_filtered_collection_restores_the_kept_models_in_order()
    {
        foreach (['u1', 'u2', 'u3'] as $id) {
            TenantUser::create(['id' => $id, 'tenant_id' => 't1', 'name' => $id]);
        }
        $kept = TenantUser::orderByDesc('id')->get()->filter(fn ($user) => $user->id !== 'u3');
        $this->assertSame([1, 2], $kept->keys()->all(), 'the filtered collection must not start at index 0');

        $restored = $this->roundTrip(QueueableCompositeCollection::for($kept));

        $this->assertSame(['u2', 'u1'], $restored->pluck('id')->all());
    }

    public function test_models_without_composite_key_are_rejected_at_wrap_time()
    {
        User::create([]);

        $this->expectException(InvalidUsageException::class);
        $this->expectExceptionMessage(User::class);

        QueueableCompositeCollection::for(User::all());
    }

    public function test_soft_deleted_members_are_restored_like_serializes_models()
    {
        SoftDeleteTenantUser::create(['id' => 'u1', 'tenant_id' => 't1', 'name' => 'a']);
        SoftDeleteTenantUser::create(['id' => 'u2', 'tenant_id' => 't1', 'name' => 'b']);
        $bag = QueueableCompositeCollection::for(SoftDeleteTenantUser::all());
        $serialized = serialize($bag);

        SoftDeleteTenantUser::where('id', 'u2')->first()->delete();

        $restored = unserialize($serialized)->restore();

        $this->assertSame(['u1', 'u2'], $restored->pluck('id')->sort()->values()->all());
    }

    public function test_custom_collection_class_is_preserved()
    {
        $model = new class() extends TenantUser {
            protected $table = 'tenant_users';

            public function newCollection(array $models = [])
            {
                return new class($models) extends EloquentCollection {
                };
            }
        };
        $model->newQuery()->create(['id' => 'u1', 'tenant_id' => 't1']);
        $collection = $model->newQuery()->get();

        $restored = $this->roundTrip(QueueableCompositeCollection::for($collection));

        $this->assertSame(get_class($collection), get_class($restored));
        $this->assertCount(1, $restored);
    }
}
