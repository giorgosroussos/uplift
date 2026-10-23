<?php

use App\Modules\Activities\Actions\BookSlot;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Booking;
use App\Modules\Activities\Models\Slot;
use App\Modules\Activities\Models\SlotActivity;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
| UC-016, SCOPE-011, SCOPE-028: a slot never gets more bookings than its
| capacity, also under concurrent requests. Two processes (forked, each
| with its own database connection) book the last place of a slot at the
| same time: they are held back by a lock on the activity row and released
| together, so both run BookSlot concurrently. The data must be committed
| for the other connections to see it, hence truncation (before and after
| the test) instead of a wrapping transaction.
*/

uses(DatabaseTruncation::class);

// Leave no committed rows behind for the tests that run in a transaction.
afterEach(function () {
    $this->truncateTablesForAllConnections();
});

/**
 * Runs BookSlot for the student in a forked child process with its own
 * connection and returns the child's pid; the child writes "ok" or
 * "refused" to the file and then dies without running any shutdown code
 * (which would close the parent's inherited connections).
 */
function scBookInChild(User $student, SlotActivity $slotActivity, Slot $slot, string $resultFile): int
{
    $pid = pcntl_fork();

    if ($pid !== 0) {
        return $pid;
    }

    try {
        app(BookSlot::class)->handle($student, $slotActivity, ['slot_id' => $slot->id]);
        $result = 'ok';
    } catch (ValidationException) {
        $result = 'refused';
    } catch (Throwable $e) {
        $result = 'error: '.$e->getMessage();
    }

    file_put_contents($resultFile, $result);
    posix_kill(getmypid(), SIGKILL);

    exit(1);
}

it('lets exactly one of two students book the last place of a slot at the same time', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('Needs the pcntl extension.');
    }

    config(['tenancy.domain' => 'uplift.test']);
    $organization = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($organization);

    $teacher = User::factory()->teacher()->create();
    $space = CourseSpace::factory()->for($teacher, 'teacher')->create();
    $students = User::factory()->student()->count(3)->create();

    foreach ($students as $student) {
        Enrolment::factory()->for($space, 'courseSpace')->for($student, 'student')->create();
    }

    $activity = Activity::factory()->ofKind(ActivityKind::SlotBooking)->for($space, 'courseSpace')->published()->create();
    $slotActivity = SlotActivity::factory()->for($activity, 'activity')->withSlots(['+1 day 10:00' => 2])->create();
    $slot = Slot::query()->where('slot_activity_id', $activity->id)->sole();

    // One place is taken already: the two children race for the last one.
    $taken = new Booking;
    $taken->slot_id = $slot->id;
    $taken->student_id = $students[2]->id;
    $taken->save();

    // Hold both children back behind a lock on the activity row, on a connection of its own.
    config(['database.connections.barrier' => config('database.connections.'.config('database.default'))]);
    $barrier = DB::connection('barrier');
    $barrier->beginTransaction();
    $barrier->select('SELECT id FROM activities WHERE id = ? FOR UPDATE', [$activity->id]);

    // The children open their own default connection after the fork.
    DB::disconnect();

    $dir = sys_get_temp_dir().'/slot-race-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $files = ["{$dir}/a", "{$dir}/b"];
    $pids = [
        scBookInChild($students[0], $slotActivity, $slot, $files[0]),
        scBookInChild($students[1], $slotActivity, $slot, $files[1]),
    ];

    // Both children are now waiting for the lock; release them together.
    usleep(500_000);
    $barrier->commit();
    DB::disconnect('barrier');

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $results = array_map(static fn (string $file): string => (string) @file_get_contents($file), $files);
    array_map('unlink', array_filter($files, 'is_file'));
    rmdir($dir);
    sort($results);

    expect($results)->toBe(['ok', 'refused'])
        ->and(Booking::query()->where('slot_id', $slot->id)->count())->toBe(2);
});
