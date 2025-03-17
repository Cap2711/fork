<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\{
    LearningPathController,
    UnitController,
    LessonController,
    SectionController,
    ExerciseController,
    QuizController,
    QuizQuestionController,
    VocabularyController,
    GuideController,
    UserProgressController
};

Route::middleware(['auth:sanctum'])->group(function () {
    // Learning Content Routes - Read-only access for regular users
    // These routes should only provide access to published content
    
    // Learning Paths
    Route::get('learning-paths', [LearningPathController::class, 'index']);
    Route::get('learning-paths/{learningPath}', [LearningPathController::class, 'show']);
    Route::get('learning-paths/{learningPath}/progress', [LearningPathController::class, 'progress']);
    
    // Units
    Route::get('units', [UnitController::class, 'index']);
    Route::get('units/{unit}', [UnitController::class, 'show']);
    
    // Lessons
    Route::get('lessons', [LessonController::class, 'index']);
    Route::get('lessons/{lesson}', [LessonController::class, 'show']);
    
    // Sections
    Route::get('sections', [SectionController::class, 'index']);
    Route::get('sections/{section}', [SectionController::class, 'show']);
    
    // Exercises
    Route::get('exercises', [ExerciseController::class, 'index']);
    Route::get('exercises/{exercise}', [ExerciseController::class, 'show']);
    
    // Quizzes
    Route::get('quizzes', [QuizController::class, 'index']);
    Route::get('quizzes/{quiz}', [QuizController::class, 'show']);
    
    // Quiz Questions
    Route::get('quiz-questions', [QuizQuestionController::class, 'index']);
    Route::get('quiz-questions/{quizQuestion}', [QuizQuestionController::class, 'show']);
    
    // Vocabulary
    Route::get('vocabulary', [VocabularyController::class, 'index']);
    Route::get('vocabulary/{vocabulary}', [VocabularyController::class, 'show']);
    
    // Guide Entries
    Route::get('guide-entries', [GuideController::class, 'index']);
    Route::get('guide-entries/{guideEntry}', [GuideController::class, 'show']);

    // User Progress Routes - Allow users to track their own progress
    Route::prefix('progress')->group(function () {
        Route::get('/', [UserProgressController::class, 'index']);
        Route::post('/{type}/{id}', [UserProgressController::class, 'store']);
        Route::get('/{type}/{id}', [UserProgressController::class, 'show']);
        Route::put('/{type}/{id}', [UserProgressController::class, 'update']);
    });
});
