<?php

use App\Data\User\Models\SitelliteUser;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

abstract class LmsApiController extends EljurApiController
{
    /**
     * Обрабатывает результат валидации и возвращает ответ с ошибкой
     *
     * @param ValidationException $e
     *
     * @return EljurRawResponse
     */
    public function handleValidateException(ValidationException $e): EljurRawResponse
    {
        $validator = $e->validator;
        $failedRules = $validator->failed();
        $failedRuleCode = key($failedRules);
        $messagesBag = $validator->getMessageBag();
        $messages = $messagesBag->get($failedRuleCode);
        $message = reset($messages);

        return $this->sendError([
            'message' => $message,
            'param' => $failedRuleCode,
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param ModelNotFoundException $e
     *
     * @return EljurRawResponse
     */
    public function handleModelNotFoundException(ModelNotFoundException $e): EljurRawResponse
    {
        return $this->sendError([
            'message' => $e->getMessage(),
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Вернуть ответ с ошибкой
     *
     * @param string $error
     * @param int $code
     *
     * @return EljurRawResponse
     */
    public function sendError($error = '', $code = Response::HTTP_INTERNAL_SERVER_ERROR): EljurRawResponse
    {
        return new EljurRawResponse(new EljurResult(false, $error), $code);
    }

    /**
     * @throws AuthenticationException
     */
    protected function getAuthUser(): SitelliteUser
    {
        $user = session_get_user_model();
        if (!$user) {
            throw new AuthenticationException();
        }
        return $user;
    }
}
