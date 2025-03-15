'use client';

import QuizForm from '@/components/admin/forms/QuizForm';
import { getQuiz } from '@/app/_actions/admin/quiz-actions';
import { useEffect, useState } from 'react';
import { 
  Question,
  QuestionType,
  MultipleChoiceQuestion,
  TranslationQuestion,
  FillInBlankQuestion,
  MatchingQuestion,
  ListenTypeQuestion,
  SpeakRecordQuestion,
  TrueFalseQuestion,
  Media,
} from '@/components/admin/questions/types';

// Define base interface for raw question data
interface RawQuestionBase {
  type?: QuestionType;
  explanation?: string;
  difficulty_level?: string;
  order?: number;
  id?: number;
}

interface RawMultipleChoiceQuestion extends RawQuestionBase {
  question?: string;
  correct_answer?: string;
  options?: string[];
}

interface RawTranslationQuestion extends RawQuestionBase {
  text?: string;
  correct_translation?: string;
  alternatives?: string[];
  source_language?: string;
  target_language?: string;
}

interface RawFillInBlankQuestion extends RawQuestionBase {
  sentence?: string;
  blanks?: Array<{
    position: number;
    correct_answer: string;
    alternatives?: string[];
  }>;
}

interface RawMatchingQuestion extends RawQuestionBase {
  pairs?: Array<{
    left: string;
    right: string;
    media?: Media;
  }>;
}

interface RawListenTypeQuestion extends RawQuestionBase {
  audio?: Media;
  correct_text?: string;
  alternatives?: string[];
  language?: string;
}

interface RawSpeakRecordQuestion extends RawQuestionBase {
  text_to_speak?: string;
  correct_pronunciation?: string;
  language?: string;
  example_audio?: Media;
}

interface RawTrueFalseQuestion extends RawQuestionBase {
  statement?: string;
  is_true?: boolean;
}

interface QuizData {
  id: number;
  lesson_id: number;
  title: string;
  description: string;
  passing_score: number;
  time_limit: number;
  difficulty_level: string;
  is_published: boolean;
  questions: Question[];
  created_at: string;
  updated_at: string;
}

function createMultipleChoiceQuestion(data: RawMultipleChoiceQuestion): MultipleChoiceQuestion {
  return {
    type: 'multiple-choice',
    question: data.question || '',
    correct_answer: data.correct_answer || '',
    options: data.options || ['', '', '', ''],
    explanation: data.explanation || '',
    difficulty_level: data.difficulty_level || 'normal',
    order: data.order || 0,
  };
}

function createTranslationQuestion(data: RawTranslationQuestion): TranslationQuestion {
  return {
    type: 'translation',
    text: data.text || '',
    correct_translation: data.correct_translation || '',
    alternatives: data.alternatives || [],
    source_language: data.source_language || 'en',
    target_language: data.target_language || 'es',
    explanation: data.explanation || '',
    difficulty_level: data.difficulty_level || 'normal',
    order: data.order || 0,
  };
}

function createFillInBlankQuestion(data: RawFillInBlankQuestion): FillInBlankQuestion {
  return {
    type: 'fill-in-blank',
    sentence: data.sentence || '',
    blanks: data.blanks || [{ position: 0, correct_answer: '', alternatives: [] }],
    explanation: data.explanation || '',
    difficulty_level: data.difficulty_level || 'normal',
    order: data.order || 0,
  };
}

function createMatchingQuestion(data: RawMatchingQuestion): MatchingQuestion {
  return {
    type: 'matching',
    pairs: data.pairs || [{ left: '', right: '' }, { left: '', right: '' }],
    explanation: data.explanation || '',
    difficulty_level: data.difficulty_level || 'normal',
    order: data.order || 0,
  };
}

function createListenTypeQuestion(data: RawListenTypeQuestion): ListenTypeQuestion {
  return {
    type: 'listen-type',
    audio: data.audio || { type: 'audio', url: '', alt: '' },
    correct_text: data.correct_text || '',
    alternatives: data.alternatives || [],
    language: data.language || 'en',
    explanation: data.explanation || '',
    difficulty_level: data.difficulty_level || 'normal',
    order: data.order || 0,
  };
}

function createSpeakRecordQuestion(data: RawSpeakRecordQuestion): SpeakRecordQuestion {
  return {
    type: 'speak-record',
    text_to_speak: data.text_to_speak || '',
    correct_pronunciation: data.correct_pronunciation || '',
    language: data.language || 'en',
    example_audio: data.example_audio || { type: 'audio', url: '', alt: '' },
    explanation: data.explanation || '',
    difficulty_level: data.difficulty_level || 'normal',
    order: data.order || 0,
  };
}

function createTrueFalseQuestion(data: RawTrueFalseQuestion): TrueFalseQuestion {
  return {
    type: 'true-false',
    statement: data.statement || '',
    is_true: data.is_true ?? false,
    explanation: data.explanation || '',
    difficulty_level: data.difficulty_level || 'normal',
    order: data.order || 0,
  };
}

function transformQuestion(questionData: RawQuestionBase): Question {
  const type = questionData.type || 'multiple-choice';
  switch (type as QuestionType) {
    case 'multiple-choice':
      return createMultipleChoiceQuestion(questionData as RawMultipleChoiceQuestion);
    case 'translation':
      return createTranslationQuestion(questionData as RawTranslationQuestion);
    case 'fill-in-blank':
      return createFillInBlankQuestion(questionData as RawFillInBlankQuestion);
    case 'matching':
      return createMatchingQuestion(questionData as RawMatchingQuestion);
    case 'listen-type':
      return createListenTypeQuestion(questionData as RawListenTypeQuestion);
    case 'speak-record':
      return createSpeakRecordQuestion(questionData as RawSpeakRecordQuestion);
    case 'true-false':
      return createTrueFalseQuestion(questionData as RawTrueFalseQuestion);
    default:
      return createMultipleChoiceQuestion(questionData as RawMultipleChoiceQuestion);
  }
}

export default function EditQuiz({ params }: { params: { id: string } }) {
  const [quiz, setQuiz] = useState<QuizData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadQuiz = async () => {
      const result = await getQuiz(parseInt(params.id));
      if (result.error) {
        setError(result.error);
      } else if (result.data) {
        const transformedData: QuizData = {
          ...result.data,
          time_limit: result.data.time_limit || 0,
          questions: result.data.questions.map(transformQuestion),
        };

        setQuiz(transformedData);
      }
      setLoading(false);
    };

    loadQuiz();
  }, [params.id]);

  if (loading) {
    return <div className="flex justify-center items-center h-48">Loading...</div>;
  }

  if (error) {
    return (
      <div className="text-red-500 p-4 rounded-md bg-red-50 mb-4">
        Error: {error}
      </div>
    );
  }

  if (!quiz) {
    return (
      <div className="text-red-500 p-4 rounded-md bg-red-50 mb-4">
        Quiz not found
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Edit Quiz</h2>
        <p className="text-muted-foreground">
          Update quiz details and questions
        </p>
      </div>

      <QuizForm
        lessonId={quiz.lesson_id}
        initialData={{
          id: quiz.id,
          title: quiz.title,
          description: quiz.description,
          passing_score: quiz.passing_score,
          time_limit: quiz.time_limit,
          difficulty_level: quiz.difficulty_level,
          is_published: quiz.is_published,
          questions: quiz.questions,
        }}
      />
    </div>
  );
}