<?php

namespace App\Http\Controllers\Api\encuestas;

use App\Models\User;
use App\Models\Survey;
use Illuminate\Support\Arr;
use App\Models\SurveyAnswer;
use Illuminate\Http\Request;
use App\Models\SurveyQuestion;
use App\Traits\UploadableFile;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Survey\GradeRequest;
use App\Http\Requests\Survey\SurveyStoreRequest;
use App\Http\Requests\Survey\UpdateSurveyRequest;
use App\Http\Requests\Survey\EvalueesSurveyRequest;
use App\Http\Requests\Survey\StoreSurveyAnswerRequest;
use App\Models\Grade;

class SurveyController extends Controller
{
    use UploadableFile;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Survey::with(['question', 'evaluee', 'evaluee.empleado'])->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SurveyStoreRequest $request)
    {
        $data = $request->validated();

        $survey = Survey::create($data);

        if ($request->hasFile('image')) {
            $pic = $request->file('image');
            $path = $this->uploadOne($pic, $survey->default_path_folder, 's3');
            $updateData = ['image' => $path];
            $survey->update($updateData);
        }

        // Create new questions
        foreach ($data['questions'] as $question) {
            $question['survey_id'] = $survey->id;
            $this->createQuestion($question);
        }

        return response()->json($survey);
    }

    /**
     * Display the specified resource.
     */
    public function show(Survey $survey)
    {
        return response()->json($survey->load(['question']));
    }

    public function showPerEvaluee(User $userId)
    {
        $evaluees = $userId->evaluee()->with(['question','question.answer'])->get();

        return response()->json($evaluees);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSurveyRequest $request, Survey $survey)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($survey->image) {
                Storage::disk('s3')->delete($survey->image);
            }
            $pic = $request->file('image');
            $path = $this->uploadOne($pic, $survey->default_path_folder, 's3');
            $updateData = ['image' => $path];
            $survey->update($updateData);
        }

        // Update survey in the database
        $survey->update($data);

        // Get ids as plain array of existing questions
        $existingIds = $survey->question()->pluck('id')->toArray();
        // Get ids as plain array of new questions
        $newIds = Arr::pluck($data['questions'], 'id');
        // Find questions to delete
        $toDelete = array_diff($existingIds, $newIds);
        //Find questions to add
        $toAdd = array_diff($newIds, $existingIds);

        // Delete questions by $toDelete array
        SurveyQuestion::destroy($toDelete);

        // Create new questions
        foreach ($data['questions'] as $question) {
            if (in_array($question['id'], $toAdd)) {
                $question['survey_id'] = $survey->id;
                $this->createQuestion($question);
            }
        }

        // Update existing questions
        $questionMap = collect($data['questions'])->keyBy('id');
        $questions = $survey->question;
        //return response()->json($questions);
        foreach ($questions as $question) {
            if (isset($questionMap[$question->id])) {
                $this->updateQuestion($question, $questionMap[$question->id]);
            }
        }

        return response()->json($survey->load('question'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Survey $survey)
    {
        if ($survey->image) {
            Storage::disk('s3')->delete($survey->image);
        }

        $survey->delete();

        return response('', 204);
    }

    private function createQuestion($data)
    {
        // if (is_array($data['data'])) {
        //     $data['data'] = json_encode($data['data']);
        // }

        $validator = Validator::make($data, [
            'question' => ['required', 'string'],
            'type' => ['required', Rule::in([
                Survey::TYPE_TEXT,
                Survey::TYPE_TEXTAREA,
                Survey::TYPE_SELECT,
                Survey::TYPE_RADIO,
                Survey::TYPE_CHECKBOX,
            ])],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string'],
            'data' => ['present'],
            'survey_id' => ['exists:App\Models\Survey,id']
        ]);

        $surveyQuestion = SurveyQuestion::create($validator->validated());

        if (isset($data['image'])) {
            $pic = $data['image'];
            $path = $this->uploadOne($pic, $surveyQuestion->default_path_folder, 's3');
            $updateData = ['image' => $path];
            $surveyQuestion->update($updateData);
        }

        return $surveyQuestion;
    }

    private function updateQuestion(SurveyQuestion $surveyQuestion, $data)
    {
        // if (is_array($data['data'])) {
        //     $data['data'] = json_encode($data['data']);
        // }
        $validator = Validator::make($data, [
            'id' => ['exists:App\Models\SurveyQuestion,id'],
            'question' => ['required', 'string'],
            'type' => ['required', Rule::in([
                Survey::TYPE_TEXT,
                Survey::TYPE_TEXTAREA,
                Survey::TYPE_SELECT,
                Survey::TYPE_RADIO,
                Survey::TYPE_CHECKBOX,
            ])],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string'],
            'data' => ['present'],
        ]);

        if (isset($data['image'])) {
            if ($surveyQuestion->image) {
                Storage::disk('s3')->delete($surveyQuestion->image);
            }
            $pic = $data['image'];
            $path = $this->uploadOne($pic, $surveyQuestion->default_path_folder, 's3');
            $updateData = ['image' => $path];
            $surveyQuestion->update($updateData);
        }

        return $surveyQuestion->update($validator->validated());
    }

    public function storeAnswer(StoreSurveyAnswerRequest $request)
    {
        $surveyAnswer = SurveyAnswer::create($request->validated());
        return response()->json($surveyAnswer);
    }

    public function getAnswerUserForSurvey($surveyId, $userId)
    {
        $questions = Survey::find($surveyId)->question;

        $answers = SurveyAnswer::whereIn('question_id', $questions->pluck('id'))
            ->where('evaluee_id', $userId)
            ->get();

        return response()->json($answers);
    }

    function updateComment(SurveyAnswer $answer, Request $request)
    {
        $answer->comments = $request->comments;
        $answer->rating = $request->rating;

        $answer->save();

        return response()->json($answer);
    }


    public function storeEvaluees(Survey $survey, EvalueesSurveyRequest $request)
    {
        $empleadoIds = $request->evaluees;

        $survey->evaluee()->sync($empleadoIds);

        return response()->json(['message' => 'Empleados asignados correctamente a la encuesta']);
    }

    public function storeGrade(GradeRequest $request)
    {
        return response()->json(Grade::create($request->validated()));
    }

    public function getForGrade(User $evaluee, Survey $survey)
    {
        // Obtener el total de preguntas de la encuesta
        $totalQuestions = $survey->question->count();

        // Obtener las respuestas del evaluado para esta encuesta
        $responses = DB::table('survey_answers')
            ->where('evaluee_id', $evaluee->id)
            ->whereIn('question_id', $survey->question->pluck('id'))
            ->get();

        // Contar el total de respuestas
        $totalResponses = $responses->count();

        // Contar las respuestas correctas, incorrectas y no contestadas
        $correctResponses = $responses->where('rating', true)->count();
        $incorrectResponses = $responses->where('rating', false)->count();
        $ungradedResponses = $responses->whereNull('rating')->count();
        $unansweredResponses = $totalQuestions - $totalResponses;

        // Calcular el promedio de respuestas correctas
        $averageGrade = $totalQuestions > 0 ? ($correctResponses / $totalQuestions) * 100 : 0;


        return response()->json([
            'total_questions' => $totalQuestions,
            'total_responses' => $totalResponses,
            'correct_responses' => $correctResponses,
            'incorrect_responses' => $incorrectResponses,
            'unanswered_responses' => $unansweredResponses,
            'ungraded_responses' => $ungradedResponses,
            'average_grade' => $averageGrade,
        ]);
    }

    public function getGradesForEvaluee(User $evaluee)
    {
        // Obtener todas las calificaciones para el evaluado especificado
        $grades = Grade::where('evaluee_id', $evaluee->id)->with('survey')->get();
        // Devolver las calificaciones en formato JSON
        return response()->json($grades);
    }
}