<?php
namespace Tests\Feature;
use Tests\TestCase; use Tests\Support\AccessFixtures; use App\Support\Access\AccessService; use Illuminate\Foundation\Testing\RefreshDatabase; use PHPUnit\Framework\Attributes\Test;
class GskFoundationTest extends TestCase { use RefreshDatabase, AccessFixtures;
 #[Test] public function health_endpoint_is_available():void {$this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status','ok');}
 #[Test] public function welfare_report_requires_valid_input():void {$this->postJson('/api/v1/welfare-cases',[])->assertUnprocessable();}
 #[Test] public function anonymous_welfare_report_returns_reference_and_one_time_credential():void {$this->postJson('/api/v1/welfare-cases',['is_anonymous'=>true,'category'=>'other','severity'=>'standard','description'=>str_repeat('A sufficiently detailed report. ',2)])->assertCreated()->assertJsonStructure(['reference','followup_credential','message']);}
 #[Test] public function verified_employer_gate_must_be_enforced_before_vacancy_publish():void {$this->markTestIncomplete('Implement after employer verification workflow and route are built.');}
 #[Test] public function consent_withdrawal_blocks_future_consent_based_disclosure():void {$this->markTestIncomplete('Implement when disclosure service and policy basis are finalised.');}
 #[Test] public function recurring_donation_requires_provider_supported_mandate():void {$this->markTestIncomplete('Implement after provider capability and mandate lifecycle are selected.');}
 #[Test] public function staff_can_only_access_explicitly_granted_permissions():void {
  $this->seedPermissions();
  $user=$this->makeStaff('STF-F1'); $role=$this->makeRole('reader',['audit.view']);
  $access=app(AccessService::class);
  $this->assertFalse($access->can($user,'audit.view'));
  $assignment=$this->grant($user,$role);
  $this->assertTrue($access->can($user,'audit.view'));
  $this->assertFalse($access->can($user,'users.manage'));
  $assignment->forceFill(['revoked_at'=>now()])->save();
  $this->assertFalse($access->can($user,'audit.view'));
 }
}
