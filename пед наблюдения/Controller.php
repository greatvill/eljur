<?php

namespace App\Http\Controllers\Api\PO;

use App\Facades\POFacade;
use App\Http\Exceptions\AccessDeniedHttpException;
use App\Http\Resources\PO\PedObservationCollection;
use App\Models\Action;
use App\Models\PedObservation;
use App\Models\PedObservationFile;
use App\Models\Role;
use App\Models\UserPedObservation;
use App\Services\Excel\PO\POToExcelService;
use App\Services\POGroupExport;
use App\Services\POStudentExport;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\PO\PedObservation as PedObservationResource;
use \App\Http\Controllers\Api\BaseController;
use PhpOffice\PhpSpreadsheet\Writer\Exception;

class Controller extends BaseController
{
    /**
     * @var POFacade
     */
    private $facade;
    /**
     * @var POGroupExport
     */
    private $poGroupExport;
    /**
     * @var POStudentExport
     */
    private $poStudentExport;

    public function __construct(
        POFacade $facade,
        POGroupExport $poGroupExport,
        POStudentExport $poStudentExport
    )
    {
        parent::__construct();
        $this->facade = $facade;
        $this->poGroupExport = $poGroupExport;
        $this->poStudentExport = $poStudentExport;
    }

    public function actionGet(int $id): PedObservationResource
    {
        $pedObservation = $this->facade->getPedObservation($id);
        return new PedObservationResource($pedObservation);
    }

    /**
     * @throws \Exception
     */
    public function actionCreate(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();
        if (!$user->canDo(Action::CODE_PO_CREATE)) {
            throw new AccessDeniedHttpException();
        }
        try {
            $params = $this->validate($request, [
                'student_id' => 'required|integer|min:1|exists:users,id',
                'text' => 'required|string|min:1|max:2000',
                'attachments' => 'array|nullable|max:3',
                'attachments.*.name' => 'required|string|min:1',
                'attachments.*.fid' => 'required|string|min:32|max:32',
                'attachments.*.size' => 'required|integer',
            ], [], $this->customAttrs());
        } catch (ValidationException $ex) {
            return $this->handleValidateException($ex);
        }

        DB::beginTransaction();

        try {
            $attrs = [
                'student_id' => $request->post('student_id'),
                'text' => $request->post('text'),
                'author_id' => $user->id,
            ];
            if ($user->id === User::find($attrs['student_id'])->curator_id) {
                $attrs['is_viewed'] = true;
            }
            /**
             * @var PedObservation $pedObservation
             */
            $pedObservation = PedObservation::create($attrs);
            if (!empty($params['attachments'])) {
                foreach ($params['attachments'] as $file) {
                    $file = new PedObservationFile($file);
                    $file->ped_observation_id = $pedObservation->id;
                    $file->save();
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        DB::commit();

        return new PedObservationResource($pedObservation);
    }

    /**
     * @throws \Exception
     */
    public function actionUpdate(int $id, Request $request)
    {
        try {
            $attrs = $this->validate($request, [
                'text' => 'string|min:1|max:2000',
                'note' => 'nullable|string',
                'is_favorite' => 'boolean',
                'is_viewed' => 'boolean',
                'attachments' => 'array|nullable|max:3',
                'attachments.*.name' => 'required|string|min:1',
                'attachments.*.fid' => 'required|string|min:32|max:32',
                'attachments.*.size' => 'required|integer',
            ], [], $this->customAttrs());
        } catch (ValidationException $ex) {
            return $this->handleValidateException($ex);
        }
        /**
         * @var PedObservation|null $pedObservation
         */
        $pedObservation = $this->facade->getPedObservation($id);
        /** @var User $user */
        $user = auth()->user();
        if ($pedObservation->author_id !== $user->id
            && $pedObservation->student->curator_id !== $user->id) {
            throw new AccessDeniedHttpException();
        }

        try {
            if (isset($attrs['text'])) {
                $pedObservation->text = $attrs['text'];
            }

            if (isset($attrs['is_viewed'])) {
                $pedObservation->is_viewed = $attrs['is_viewed'];
            } else if ($pedObservation->isDirty()) {
                if ($pedObservation->student->curator_id === $user->id) {
                    $pedObservation->is_viewed = true;
                } else {
                    $pedObservation->is_viewed = false;
                }
            }
            $pedObservation->save();

            if (array_key_exists('note', $attrs) || isset($attrs['is_favorite'])) {
                /**
                 * @var UserPedObservation $userPedObservation
                 */
                $userPedObservation = UserPedObservation::firstOrCreate([
                    'ped_observation_id' => $pedObservation->id,
                    'user_id' => $user->id,
                ]);

                if (array_key_exists('note', $attrs)) {
                    $userPedObservation->note = $attrs['note'];
                }
                if (isset($attrs['is_favorite'])) {
                    $userPedObservation->is_favorite = $attrs['is_favorite'];
                }
                $userPedObservation->save();
            }

            if (array_key_exists('attachments', $attrs)) {
                $currentFiles = $pedObservation->pedObservationFiles->keyBy('fid');
                $fidsNew = array_column($attrs['attachments'], 'fid');
                $fidsCurrent = $currentFiles->pluck('fid')->toArray();
                sort($fidsCurrent);
                sort($fidsNew);
                if ($fidsCurrent !== $fidsNew) {
                    foreach ($attrs['attachments'] as $file) {
                        if (empty($currentFiles[$file['fid']])) {
                            $file = new PedObservationFile($file);
                            $file->ped_observation_id = $pedObservation->id;
                            $file->save();
                        }
                    }
                    $pedObservation->pedObservationFiles()
                        ->whereNotIn('fid', array_column($attrs['attachments'], 'fid'))
                        ->delete();

                    if (!isset($attrs['is_viewed']) && $pedObservation->student->curator_id !== $user->id) {
                        $pedObservation->is_viewed = false;
                    }
                    $pedObservation->updated_at = new Carbon();
                    $pedObservation->save();
                }
            }

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        DB::commit();
        $pedObservation->load('pedObservationFiles');

        return new PedObservationResource($pedObservation);
    }

    /**
     * @throws \Exception
     */
    public function actionDelete(int $id): JsonResponse
    {
        $pedObservation = $this->facade->getPedObservation($id);
        /** @var User $user */
        $user = auth()->user();
        if ($pedObservation->author_id !== $user->id) {
            throw new AccessDeniedHttpException();
        }
        $pedObservation->delete();
        return JsonResponse::create(null, Response::HTTP_NO_CONTENT);
    }

    public function actionMyStudyGroup(Request $request): PedObservationCollection
    {
        /**
         * @var User $user
         */
        $user = auth()->user();
        if (!$user->canDo(Action::CODE_PO_VIEW_MY_GROUP)) {
            throw new AccessDeniedHttpException();
        }
        $filter = $request->get('filter', []);
        $result = $this->facade->getByCurator($user, $filter);
        return new PedObservationCollection($result);
    }

    public function actionMy(): PedObservationCollection
    {
        /**
         * @var User $user
         */
        $user = auth()->user();
        $result = $this->facade->getByAuthor($user);
        return new PedObservationCollection($result);
    }

    public function actionMyStudyGroupStat(): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = auth()->user();
        $roles = $user->roles()->get(['code'])->pluck('code')->toArray();

        if (!in_array(Role::CODE_CURATOR, $roles, true)) {
            throw new AccessDeniedHttpException();
        }

        $students = User::query()
            ->with('pedObservations')
            ->where(sprintf('%s.curator_id', User::TABLE), $user->id)
            ->get();
        $result = $students->map(function (User $student) {
            return [
                'id' => $student->id,
                'observations_count' => $student->pedObservations->count(),
                'unviewed_observations_count' => $student->pedObservations->where('is_viewed', false)->count(),
            ];
        });
        return JsonResponse::create($result);
    }

    public function actionSetViewed(int $id): JsonResponse
    {
        $pedObservation = $this->facade->getPedObservation($id);
        /**
         * @var User $user
         */
        $user = auth()->user();
        if ($user->id !== $pedObservation->student->curator_id) {
            throw new AccessDeniedHttpException();
        }
        $pedObservation->timestamps = false;
        $pedObservation->is_viewed = true;
        $pedObservation->save();
        return JsonResponse::create(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws Exception
     */
    public function actionExportMyGroup(Request $request, string $format = 'xlsx')
    {
        try {
            $this->validate($request, [
                'curator_id' => ['integer', 'exists:users,id',]
            ]);
        } catch (ValidationException $ex) {
            return $this->handleValidateException($ex);
        }
        $idCurator = $request->get('curator_id');
        if ($idCurator) {
            $curator = User::findOrFail((int)$idCurator);
        } else {
            $curator = auth()->user();
        }
        /**
         * @var User $curator
         */
        $this->poGroupExport->export($curator, $format);
    }

    /**
     * @throws Exception
     */
    public function actionExportStudent(User $student, string $format = 'xlsx'): void
    {
        $this->poStudentExport->export($student, $format);
    }

    public function actionExport(string $format = 'xlsx'): void
    {
        /**
         * @var User $user
         */
        $user = auth()->user();
        if (!$user->canDo(Action::CODE_PO_EXPORT_ALL)) {
            throw new AccessDeniedHttpException();
        }
        if ($format === 'xlsx') {
            app(POToExcelService::class)->export();
        }
    }


    private function customAttrs(): array
    {
        return [
            'student_id' => 'Лицеист',
            'text' => 'Текст наблюдения',
            'attachments' => 'Файлы',
            'note' => 'Текстовая заметка'
        ];
    }
}
