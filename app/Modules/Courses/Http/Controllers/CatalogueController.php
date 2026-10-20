<?php

namespace App\Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Courses\Actions\BrowseCatalogue;
use App\Modules\Courses\Http\Requests\CatalogueRequest;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-011: students search and filter the active course spaces of their
 * organisation.
 */
class CatalogueController extends Controller
{
    public function __invoke(CatalogueRequest $request, BrowseCatalogue $browse): Response
    {
        Gate::authorize('browseCatalogue', CourseSpace::class);

        /** @var User $student */
        $student = $request->user();

        return Inertia::render('Courses/Catalogue', [
            ...$browse->handle($student, $request->search(), $request->teacher()),
            'filters' => [
                'search' => $request->search() ?? '',
                'teacher' => $request->teacher() ?? '',
            ],
        ]);
    }
}
