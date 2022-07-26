<?php

use App\Data\LMS\AccessHelper;
use App\Data\LMS\Models\LMSCourse;
use App\Data\LMS\Models\LMSPayload;
use App\Data\LMS\Models\LMSPayloadFile;
use App\Data\LMS\Models\LMSTheme;
use App\Data\LMS\Models\Work;
use App\Data\LMS\Models\WorkStage;
use App\Data\User\Models\SitelliteUser;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LMSController extends LmsApiController
{
    public const SAVE_AS_DRAFT = 'draft';
    public const SAVE_AS_PUBLISH = 'publish';

    public const TYPE_EVALUATION_WITHOUT_MARK = 'without_mark';
    public const TYPE_EVALUATION_MARK_WITHOUT_TYPE = 'mark_without_type';

    public const ENTITY_PAYLOAD = 'payload';
    public const ENTITY_THEME = 'theme';

    public function actionSaveTheme(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'id' => 'int',
            'name' => 'required|string',
            'course_id' => 'required|int',
        ]);
        if (!empty($data['id'])) {
            $model = LMSTheme::query()->findOrFail($data['id']);
        } else {
            $model = new LMSTheme();
        }
        $model->fill($data);
        $model->save();
        return $this->sendResult(['id' => $model->id]);
    }

    /**
     * @throws Exception
     */
    public function actionSavePayload(Request $request): EljurRawResponse
    {
        $data = PayloadValidator::validate($request);

        $courseService = new CourseService();
        $payload = $courseService->savePayload($data, $data['id'] ?? null);

        return $this->sendResult(['id' => $payload->id]);
    }

    /**
     * @param Request $request
     * @return EljurRawResponse
     * Получить студентов курса
     */
    public function actionGetStudents(Request $request): EljurRawResponse
    {
        $request->validate(['course_id' => 'required|int']);
        /**
         * @var LMSCourse $course
         */
        $course = LMSCourse::query()->findOrFail($request->get('course_id'));
        $tr = new Transition();
        $students = $tr->getClassList($course->class, $course->group_id ?? false, false, true);
        //todo merge из доп участников курса
        $students = array_values(array_map(static function ($st) {
            return [
                'id' => (int)$st->uid,
                'lastname' => $st->lastname,
                'firstname' => $st->firstname,
                'middlename' => $st->middlename,
            ];
        }, $students));
        return $this->sendResult($students);
    }

    /**
     * @throws Exception
     */
    public function actionGetEvalTypes(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'course_id' => 'required|int'
        ]);
        $authUser = session_get_user_model();
        if (!$authUser) {
            throw new AuthenticationException();
        }
        /**
         * @var LMSCourse $course
         * @var CourseService $courseService
         */
        $course = LMSCourse::query()->findOrFail($data['course_id']);
        $courseService = app(CourseService::class);
        $typeEvals = $courseService->getTypeEvals($course);
        return $this->sendResult($typeEvals);
    }

    /**
     * @param Request $request
     * @return EljurRawResponse
     * Информация о задании для оценивания
     */
    public function actionGetInfoForEval(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'task_id' => 'required|int'
        ]);
        $task = (new TaskData(Core::domainYear()))->getTaskById($data['task_id']);
        $course = (new CourseData(Core::domainYear()))->getCourse($task->course_id);
        $taskInfo = $task->toArray();
        $taskInfo['files'] = $task
            ->files()
            ->select(['id', 'fid', 'filename'])
            ->get()
            ->map(function (LMSPayloadFile $f) {
                $f->append(['link']);
                return $f;
            });
//        $taskInfo['type_eval'] = $task->typeEval ? $task->typeEval->only(['id', 'mtype', 'mtype_short']) : null;
        $res = [
            'task' => $taskInfo,
            'course' => $course,
            'students' => [],
            'works' => [],
        ];

        $members = $task->members;

        $userCache = Core::getUsersCacheFacade();
        if ($members->isNotEmpty()) {
            $studentsIds = $members->pluck('student_id')->toArray();
        } else {
            $class = $task->course->class ?? null;
            if ($class) {
                $j = new Journal();
                $studentsIds = array_keys($j->getStudentList($class));
            }
        }

        if (!empty($studentsIds)) {
            $userCache->prefetchUsersByIds($studentsIds);
            $works = Work::query()
                ->with(['stage'])
                ->where('payload_id', $task->id)
                ->whereIn('student_id', $studentsIds)
                ->get()
                ->keyBy('student_id');
            $workData = new WorkData();
            foreach ($studentsIds as $studentId) {
                /**
                 * @var SitelliteUser|null $student
                 */
                $student = $userCache->getById($studentId);
                if ($student) {
                    $student = $student->only(['id', 'lastname', 'firstname', 'middlename', 'fullname']);
                    /**
                     * @var Work $work
                     * @var WorkStage $stage
                     */
                    $work = $works->get($studentId);
                    $stage = $work->stage;
                    $student['task_id'] = $task->id;
                    $student['work'] = $task->id;
                    $student['work_id'] = $work->id ?? null;
                    $student['work_status'] = $stage->status_name;
                    $student['work_status_id'] = $stage->status;
                    $student['work_mark'] = $stage->mark;
                    $student['student_id'] = $student['id'];
                    $student['work'] = $work ? $workData->prepareWork($work) : null;
                    $student['student'] = $student;
                    $res['students'][] = $student;
                }
            }
        }

        return $this->sendResult($res);
    }

    /**
     * @throws AuthenticationException
     */
    public function actionGetCourse(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'course_id' => 'required|int'
        ]);
        /**
         * @var LMSCourse $course
         * @var CourseService $courseService
         */
        $course = LMSCourse::query()->findOrFail($data['course_id']);
        $courseService = app(CourseService::class);
        $composition = $courseService->getComposition($course);
        $actions = AccessHelper::getActionsByCourse($course);
        $authUser = session_get_user_model();
        if (!$authUser) {
            throw new AuthenticationException();
        }
        $initData = $courseService->getInitCreatingData($course, $authUser);
        return $this->sendResult(
            array_merge($composition, [
                'actions' => $actions,
                'students' => $initData['students'] ?? [],
                'evaluation_types' => $initData['typeEvals'] ?? [],
                'global_mark_max' => $initData['global_mark_max'] ?? null,
            ])
        );
    }

    public function actionFix(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'id' => 'required|int',
            'entity' => ['required', Rule::in([self::ENTITY_PAYLOAD, self::ENTITY_THEME])]
        ]);

        /**
         * @var CourseService $service
         */
        $service = app(CourseService::class);
        $map = [
            self::ENTITY_PAYLOAD => [$service, 'fixPayload'],
            self::ENTITY_THEME => [$service, 'fixTheme'],
        ];
        $entity = $map[$data['entity']]($data['id']);
        return $this->sendResult(['id' => $entity->id]);
    }

    public function actionUnfix(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'id' => 'required|int',
            'entity' => ['required', Rule::in([self::ENTITY_PAYLOAD, self::ENTITY_THEME])]
        ]);

        /**
         * @var CourseService $service
         */
        $service = app(CourseService::class);
        $map = [
            self::ENTITY_PAYLOAD => [$service, 'unfixPayload'],
            self::ENTITY_THEME => [$service, 'unfixTheme'],
        ];
        $entity = $map[$data['entity']]($data['id']);
        return $this->sendResult(['id' => $entity->id]);
    }

    /**
     * @throws Exception
     */
    public function actionVerifyWork(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'task_id' => 'required|int',
            'student_id' => 'required|int',
            'mark' => 'string',
            'commentary' => 'string|nullable',
        ]);
        $data['initiator_id'] = session_uid();
        $work = (new WorkManager())->verifyWork($data);
        return $this->sendResult(['work' => $work]);
    }

    public function actionGetWork(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'id' => 'required|int',
        ]);

        /**
         * @var Work $work
         */
        $work = Work::query()
            ->with([
                'content' => function (Relation $relation) {
                    return $relation->select(['id', 'work_id', 'text']);
                },
                'stage' => function (Relation $relation) {
                    return $relation->select(['id', 'work_id', 'status', 'created_at', 'mark']);
                },
            ])
            ->findOrFail($data['id']);
        if ($work->stage) {
            $work->stage->append(['statusName']);
        }

        return $this->sendResult($work);
    }

    /**
     * @param Request $request
     * @return EljurRawResponse
     * Получить данные необходимые для создания задачи/материала
     * @throws AuthenticationException
     */
    public function actionInitCreatingPayload(Request $request): EljurRawResponse
    {
        $authUser = session_get_user_model();
        if (!$authUser) {
            throw new AuthenticationException();
        }
        $request->validate(['course_id' => 'required|int']);
        /**
         * @var LMSCourse $course
         */
        $course = LMSCourse::query()->findOrFail($request->get('course_id'));
        $courseService = new CourseService();

        return $this->sendResult($courseService->getInitCreatingData($course, $authUser));
    }

    /**
     * @throws Exception
     */
    public function actionSaveAs(Request $request): EljurRawResponse
    {
        $request->validate([
            'id' => 'required|int',
            'save_as' => ['required', Rule::in([self::SAVE_AS_DRAFT, self::SAVE_AS_PUBLISH]),]
        ]);
        /**
         * @var LMSPayload $payload
         */
        $attrsForUpdate = [];
        $service = new CourseService();
        $payload = LMSPayload::query()->findOrFail($request->get('id'));
        if ($request->get('save_as') === self::SAVE_AS_PUBLISH && PayloadValidator::canPublished($payload, true)) {
            if (!$payload->members()->exists()) {
                $attrsForUpdate['published_at'] = now()->format('Y-m-d H:i:s');
            } else {
                $service->publishForCurrentMembers($payload);
            }
        } elseif ($request->get('save_as') === self::SAVE_AS_DRAFT) {
            $attrsForUpdate['published_at'] = null;
            $service->draftForCurrentMembers($payload);
        }

        if ($attrsForUpdate) {
            $payload
                ->fill($attrsForUpdate)
                ->save();
        }

        return $this->sendResult(['id' => $payload->id]);
    }

    /**
     * @throws Exception
     */
    public function actionDeletePayload(Request $request): EljurRawResponse
    {
        $request->validate([
            'id' => 'required|int',
        ]);

        /**
         * @var LMSPayload $payload
         */
        $payload = LMSPayload::query()->findOrFail($request->get('id'));
        if ($payload->works()->exists()) {
            throw new \RuntimeException(trans('course.errors.works_exist'));
        }
        db()->transactionStart();
        try {
            $payload->members()->delete();
            $payload->delete();
        } catch (Exception $e) {
            db()->transactionRollback();
            throw new \RuntimeException(trans('course.errors.payload_not_deleted'));
        }
        db()->transactionCommit();

        return $this->sendResult();
    }

    /**
     * @throws Exception
     */
    public function actionDeleteTheme(Request $request): EljurRawResponse
    {
        $request->validate([
            'id' => 'required|int',
        ]);

        /**
         * @var LMSTheme $theme
         */
        $theme = LMSTheme::query()->findOrFail($request->get('id'));
        db()->transactionStart();
        try {
            $payloadIds = $theme->payloads()->pluck('id');
            if ($payloadIds->isNotEmpty()) {
                LMSPayload::query()
                    ->whereIn('id', $payloadIds->toArray())
                    ->update(['theme_id' => null]);
            }
            $theme->delete();
        } catch (Exception $e) {
            db()->transactionRollback();
            throw new \RuntimeException(trans('course.errors.theme_not_deleted'));
        }
        db()->transactionCommit();

        return $this->sendResult();
    }

    /**
     * @throws Exception
     */
    public function actionSetSorting(Request $request): EljurRawResponse
    {
        $request->validate([
            'id' => 'required|int',
            'fixed' => 'array',
            'unfixed' => 'array',
            //'theme:*' => 'array
        ]);

        /**
         * @var LMSCourse $course
         */
        $course = LMSCourse::query()->findOrFail($request->get('id'));
        (new SortManager($course))->sort($request->all());
        return $this->sendResult();
    }
}
