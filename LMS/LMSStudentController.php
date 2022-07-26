<?php

use App\Data\LMS\Models\LMSPayload;
use App\Data\LMS\Models\Work;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class LMSStudentController extends LmsApiController
{
    public function actionInit(Request $request): EljurRawResponse
    {
        $tr = new Transition();
        $studentId = session_uid();
        $studentService = new StudentCourseService(Core::domainYear());

        return $this->sendResult([
            'student_id' => $studentId,
            'student_full_name' => getFio($studentId),
            'student_class' => $tr->getStudentClass($studentId),
            'student_role' => role()->getRole(),
            'subjects' => $studentService->getStudentCourses($studentId),
        ]);
    }

    public function actionGetCourses(Request $request): EljurRawResponse
    {
        $studentService = new StudentCourseService(Core::domainYear());
        $courses = $studentService->getStudentCourses(session_uid());
        return $this->sendResult(['items' => $courses]);
    }

    public function actionGetCourse(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer'],
        ]);

        $course = (new CourseData(Core::domainYear()))->getCourse($data['course_id']);
        $courseService = new CourseService();
        $structure = $courseService->getComposition($course);
        $initData = $courseService->getInitCreatingData($course);
        return $this->sendResult(array_merge($structure, [
            'students' => $initData['students'] ?? [],
            'evaluation_types' => $initData['typeEvals'] ?? [],
            'global_mark_max' => $initData['global_mark_max'] ?? null,
        ]));
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function actionSaveDraft(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'task_id' => ['required:work_id', 'integer'],
            'text' => ['nullable', 'string'],
            'files.*.fid' => 'string|required|max:32|min:32',
            'files.*.filename' => 'string|required',
        ]);
        $data['student_id'] = session_uid();
        (new WorkManager())->saveDraft($data);
        return $this->sendResult();
    }

    /**
     * @throws Exception
     */
    public function actionSubmitWork(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
        ]);
        $data['student_id'] = session_uid();
        (new WorkManager())->submitWork($data);
        return $this->sendResult();
    }

    /**
     * @throws Exception
     */
    public function actionRevokeWork(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
        ]);
        $data['student_id'] = session_uid();
        (new WorkManager())->revokeWork($data);
        return $this->sendResult();
    }

    /**
     * @throws BusinessLogicException
     */
    public function actionResubmitWork(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
        ]);
        $data['student_id'] = session_uid();
        (new WorkManager())->resubmitWork($data);
        return $this->sendResult();
    }

    /**
     * @throws BusinessLogicException
     */
    public function actionRemoveDraft(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
        ]);
        $data['student_id'] = session_uid();
        (new WorkManager())->removeDraft($data);
        return $this->sendResult();
    }

    public function actionSaveCommentary(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'work_id' => ['required', 'integer'],
            'content_id' => ['nullable', 'integer'],
            'text' => ['required', 'string'],
        ]);
        $data['student_id'] = session_uid();
        (new WorkManager())->saveCommentary($data);
        return $this->sendResult();
    }

    /**
     * @throws AuthenticationException
     * @throws Exception
     */
    public function actionGetTasks(Request $request): EljurRawResponse
    {
        $user = $this->getAuthUser();
        $tasks = (new TaskData(Core::domainYear()))->getTasksAssignedToStudent($user);
        return $this->sendResult([
            'tasks' => $tasks,
        ]);
    }

    /**
     * @throws Exception
     */
    public function actionGetTask(Request $request): EljurRawResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
        ]);
        $taskId = $data['task_id'];
        $data = (new StudentCourseService(Core::domainYear()))->getTask($taskId, session_uid());
        return $this->sendResult($data);
    }
}
