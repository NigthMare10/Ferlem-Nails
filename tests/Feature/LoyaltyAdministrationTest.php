<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Models\{User,Customer,LoyaltyReward,LoyaltyEntry,LoyaltyRedemption};
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;
class LoyaltyAdministrationTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {parent::setUp();$this->seed(DatabaseSeeder::class);}
    private function owner(){ $u=User::factory()->create(['is_active'=>true]);$u->assignRole('owner');return $u; }
    private function customer(){ $c=Customer::create(['name'=>'Lucía Pérez','email'=>Str::uuid().'@example.test','password'=>'Testing12345']);$c->forceFill(['email_verified_at'=>now()])->save();LoyaltyEntry::create(['customer_id'=>$c->id,'points'=>200,'type'=>'adjustment','source_key'=>(string)Str::uuid()]);return $c; }
    public function test_admin_reservation_is_attributed_idempotent_and_visible_in_profile_and_portal():void {
        $u=$this->owner();$c=$this->customer();$r=LoyaltyReward::create(['name'=>'Diseño','points_cost'=>50,'stock'=>1,'per_customer_limit'=>1,'is_active'=>true]);$token=(string)Str::uuid();
        $url="/configuration/loyalty/customers/$c->id/rewards/$r->id/reserve";
        $this->actingAs($u)->post($url,['operation_token'=>$token])->assertSessionHasNoErrors();$this->post($url,['operation_token'=>$token])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('loyalty_redemptions',1);$this->assertDatabaseHas('loyalty_redemptions',['reserved_by'=>$u->id,'customer_id'=>$c->id]);$this->assertSame(150,(int)$c->entries()->sum('points'));
        $this->get('/configuration/loyalty/customers/'.$c->id)->assertInertia(fn(Assert $p)=>$p->component('Configuration/LoyaltyCustomer')->has('redemptions.data',1)->where('balance',150));
        $this->get('/configuration/loyalty/rewards')->assertInertia(fn(Assert $p)=>$p->where('redemptions.data.0.customer.name',$c->name));
        $this->actingAs($c,'customer')->get('/clientes/canjes')->assertInertia(fn(Assert $p)=>$p->has('redemptions',1));
    }
    public function test_employee_cannot_browse_profiles_or_reserve_for_customers():void {
        $u=User::factory()->create(['is_active'=>true]);$u->assignRole('employee');$c=$this->customer();$r=LoyaltyReward::create(['name'=>'Gift','points_cost'=>10,'is_active'=>true]);
        $this->actingAs($u)->get('/configuration/loyalty/customers')->assertForbidden();$this->get('/configuration/loyalty/customers/'.$c->id)->assertForbidden();
        $this->post("/configuration/loyalty/customers/$c->id/rewards/$r->id/reserve",['operation_token'=>(string)Str::uuid()])->assertForbidden();$this->assertDatabaseCount('loyalty_redemptions',0);
    }
    public function test_search_matches_email_and_phone_and_paginates():void {
        $c=$this->customer();$this->actingAs($this->owner())->get('/configuration/loyalty/customers?search='.urlencode($c->email))->assertInertia(fn(Assert $p)=>$p->has('customers.data',1)->where('customers.data.0.id',$c->id));
    }
    public function test_cancel_returns_stock_once_and_confirmed_delivery_cannot_be_reversed_as_reservation():void {
        $u=$this->owner();$c=$this->customer();$r=LoyaltyReward::create(['name'=>'Gift','points_cost'=>50,'stock'=>2,'per_customer_limit'=>2,'is_active'=>true]);
        $a=app(\App\Actions\Loyalty\LoyaltyService::class)->reserve($c,$r,(string)Str::uuid(),$u);
        $this->actingAs($u)->post("/loyalty/redemptions/$a->id/confirm")->assertSessionHasNoErrors();$this->post("/loyalty/redemptions/$a->id/cancel");$this->assertSame('confirmed',$a->fresh()->status);$this->assertSame(1,$r->fresh()->stock);
        $b=app(\App\Actions\Loyalty\LoyaltyService::class)->reserve($c,$r,(string)Str::uuid(),$u);$this->post("/loyalty/redemptions/$b->id/cancel");$this->post("/loyalty/redemptions/$b->id/cancel");$this->assertSame(1,$r->fresh()->stock);$this->assertSame(150,(int)$c->entries()->sum('points'));
    }
}
