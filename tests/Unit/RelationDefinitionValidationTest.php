<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Compoships;
use Awobaz\Compoships\Database\Eloquent\Concerns\HasRelationships;
use Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany;
use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\Team;
use Awobaz\Compoships\Tests\Models\TrackingTask;
use Awobaz\Compoships\Tests\Models\User;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HasRelationships::class)]
#[CoversClass(BelongsToMany::class)]
class RelationDefinitionValidationTest extends TestCase
{
    private function model(): Model
    {
        return new class() extends Model {
            use Compoships;

            protected $table = 'allocations';

            public function shortLocal()
            {
                return $this->hasMany(TrackingTask::class, ['booking_id', 'vehicle_id'], ['booking_id']);
            }

            public function scalarLocal()
            {
                return $this->hasOne(TrackingTask::class, ['booking_id', 'vehicle_id'], 'booking_id');
            }

            public function scalarOwner()
            {
                return $this->belongsTo(User::class, ['user_id', 'booking_id'], 'id');
            }

            public function shortParentKey()
            {
                return $this->belongsToMany(Team::class, 'project_team', ['a', 'b'], ['c', 'd'], ['e'], ['f', 'g']);
            }

            public function shortRelatedKey()
            {
                return $this->belongsToMany(Team::class, 'project_team', ['a', 'b'], ['c', 'd'], ['e', 'h'], ['f']);
            }

            public function nonPackageRelatedWithCompositeRelatedKey()
            {
                return $this->belongsToMany(\Awobaz\Compoships\Tests\Stubs\PlainModel::class, 'project_user', 'user_id', ['project_region_code', 'project_division_id'], 'id', ['region_code', 'division_id']);
            }
        };
    }

    public function test_has_many_with_a_shorter_local_key_array_is_rejected()
    {
        $this->expectException(InvalidUsageException::class);
        $this->expectExceptionMessage('hasMany() on');

        $this->model()->shortLocal();
    }

    public function test_has_one_with_a_scalar_local_key_is_rejected()
    {
        $this->expectException(InvalidUsageException::class);

        $this->model()->scalarLocal();
    }

    public function test_belongs_to_with_a_scalar_owner_key_is_rejected()
    {
        $this->expectException(InvalidUsageException::class);

        $this->model()->scalarOwner();
    }

    public function test_belongs_to_many_with_mismatched_parent_key_arity_is_rejected()
    {
        $this->expectException(InvalidUsageException::class);

        $this->model()->shortParentKey();
    }

    public function test_belongs_to_many_with_mismatched_related_key_arity_is_rejected()
    {
        $this->expectException(InvalidUsageException::class);

        $this->model()->shortRelatedKey();
    }

    public function test_belongs_to_many_with_a_composite_related_key_to_a_non_package_model_is_rejected()
    {
        $this->expectException(InvalidUsageException::class);

        $this->model()->nonPackageRelatedWithCompositeRelatedKey();
    }

    public function test_well_formed_composite_definitions_are_accepted()
    {
        $allocation = new Allocation();

        $this->assertNotNull($allocation->trackingTasks());
        $this->assertNotNull($allocation->user());
        $this->assertNotNull((new Team())->projects());
    }

    public function test_qualified_key_name_with_an_array_primary_key_qualifies_each_column()
    {
        $model = new class() extends Model {
            use Compoships;

            protected $table = 'tenant_users';

            protected $primaryKey = ['id', 'tenant_id'];
        };

        $this->assertSame(['tenant_users.id', 'tenant_users.tenant_id'], $model->getQualifiedKeyName());
    }
}
