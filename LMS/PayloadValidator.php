<?php

use App\Data\Journal\Models\JournalControlType;
use App\Data\LMS\Models\LMSPayload;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayloadValidator
{
    public const ATTRIBUTES = [
        'type_evaluation' => 'Оценивание', // todo: use trans()
        'is_obligation' => 'Выполнение', // todo: use trans()
    ];

    public static function validate(Request $request): array
    {
        $request->validate(['type' => ['required', Rule::in(LMSPayload::getAllTypes())]]);
        $type = $request->get('type');
        $mapValidation = [
            LMSPayload::MATERIAL_TYPE => [self::class, 'validateMaterial'],
            LMSPayload::TASK_TYPE => [self::class, 'validateTask']
        ];

        return $mapValidation[$type]($request);
    }

    protected function validateTask(Request $request): array
    {
        $controlTypeIds = JournalControlType::query()->pluck('id')->toArray();
        $allowValuesForTypeEvaluation = $controlTypeIds;
        $errorMessages = [];

        if (!$request->get('is_obligation')) {
            $allowValuesForTypeEvaluation = array_merge($allowValuesForTypeEvaluation, [LMSController::TYPE_EVALUATION_MARK_WITHOUT_TYPE, LMSController::TYPE_EVALUATION_WITHOUT_MARK]);
        } else {
            $errorMessages['type_evaluation.in'] = 'Выберите вариант с определенным типом отметок';
        }
        if ($request->get('save_as') === LMSController::SAVE_AS_PUBLISH) {
            $rules = [
                'deadline_at' => 'required|date_format:Y-m-d',
                'is_obligation' => 'required|bool',
                'type_evaluation' => ['required', Rule::in($allowValuesForTypeEvaluation)],
            ];
            if ($request->get('type_evaluation') === LMSController::TYPE_EVALUATION_MARK_WITHOUT_TYPE) {
                $rules['points'] = 'required|int';
            }
        } else {
            $rules = [
                'deadline_at' => 'nullable|date_format:Y-m-d',
                'is_obligation' => 'bool|nullable',
                'type_evaluation' => ['nullable', Rule::in($allowValuesForTypeEvaluation)],
            ];
            if ($request->get('type_evaluation') === LMSController::TYPE_EVALUATION_MARK_WITHOUT_TYPE) {
                $rules['points'] = 'int';
            }
        }

        $rules = array_merge(self::baseRules(), $rules);
        return $request->validate($rules, $errorMessages, self::ATTRIBUTES);
    }

    protected function validateMaterial(Request $request): array
    {
        return $request->validate(self::baseRules());
    }

    protected static function baseRules(): array
    {
        return [
            'id' => 'int',
            'name' => 'required|string',
            'type' => 'required|string',
            'course_id' => 'required|int',
            'theme_id' => 'int|nullable',
            'members' => 'array',
            'members.*' => 'int',
            'description' => 'string',
            'files' => 'array',
            'files.*.fid' => 'string|required|max:32|min:32',
            'files.*.filename' => 'string|required',
            'save_as' => ['required', Rule::in([LMSController::SAVE_AS_DRAFT, LMSController::SAVE_AS_PUBLISH]),]
        ];
    }

    /**
     * @throws Exception
     */
    public static function canPublished(LMSPayload $payload, bool $withException = false): bool
    {
        $requiredAttrs = [];
        if ($payload->type === LMSPayload::TASK_TYPE) {
            $requiredAttrs[] = 'deadline_at';
            $requiredAttrs[] = 'is_obligation';
            $requiredAttrs[] = 'is_evaluation';
            if (!$payload->type_evaluation_id) {
                $requiredAttrs[] = 'points';
            }
        }
        foreach ($requiredAttrs as $attr) {
            if (is_null($payload->$attr)) {
                if ($withException) {
                    $attr = trans('course.payload.' . $attr) ?? $attr;
                    throw new Exception(trans('course.errors.not_set_attr', ['attr' => $attr]));
                }
                return false;
            }
        }
        return true;
    }
}
