'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { getQuiz } from '@/app/_actions/admin/quiz-actions';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { AlertDialog } from '@/components/admin/AlertDialog';
import { useRouter } from 'next/navigation';
import { deleteQuiz, toggleQuizStatus } from '@/app/_actions/admin/quiz-actions';
import { toast } from 'sonner';
import { 
  Question,
  QuestionType,
  MultipleChoiceQuestion as MultipleChoiceType,
  TranslationQuestion as TranslationType,
  FillInBlankQuestion as FillInBlankType,
  MatchingQuestion as MatchingType,
  ListenTypeQuestion as ListenType,
  SpeakRecordQuestion as SpeakRecordType
} from '@/components/admin/questions/types';
import MultipleChoiceQuestion from '@/components/admin/questions/MultipleChoiceQuestion';
import TranslationQuestion from '@/components/admin/questions/TranslationQuestion';
import FillInBlankQuestion from '@/components/admin/questions/FillInBlankQuestion';
import MatchingQuestion from '@/components/admin/questions/MatchingQuestion';
import ListenTypeQuestion from '@/components/admin/questions/ListenTypeQuestion';
import SpeakRecordQuestion from '@/components/admin/questions/SpeakRecordQuestion';

interface ApiQuestion {
  id?: number;
  type?: string;
  question?: string;
  correct_answer?: string;
  options?: string[];
  text?: string;
  correct_translation?: string;
  source_language?: string;
  target_language?: string;
  sentence?: string;
  blanks?: Array<{
    position: number;
    correct_answer: string;
    alternatives?: string[];
  }>;
  pairs?: Array<{
    left: string;
    right: string;
    media?: { type: 'image' | 'audio'; url: string; alt?: string };
  }>;
  audio?: { type: 'audio'; url: string; alt?: string };
  correct_text?: string;
  language?: string;
  text_to_speak?: string;
  correct_pronunciation?: string;
  example_audio?: { type: 'audio'; url: string; alt?: string };
  explanation?: string;
  order: number;
  media?: Array<{ type: 'image' | 'audio'; url: string; alt?: string }>;
}

interface Quiz {
  id: number;
  lesson_id: number;
  title: string;
  description: string;
  passing_score: number;
  time_limit: number | null;
  difficulty_level: string;
  is_published: boolean;
  questions: Question[];
  created_at: string;
  updated_at: string;
}

const questionTypeLabels: Record<QuestionType, string> = {
  'multiple-choice': 'Multiple Choice',
  'translation': 'Translation',
  'fill-in-blank': 'Fill in the Blanks',
  'matching': 'Matching',
  'listen-type': 'Listen and Type',
  'speak-record': 'Speak and Record',
  'true-false': 'True/False',
};

// Type guards
const isMultipleChoice = (q: Question): q is MultipleChoiceType => q.type === 'multiple-choice';
const isTranslation = (q: Question): q is TranslationType => q.type === 'translation';
const isFillInBlank = (q: Question): q is FillInBlankType => q.type === 'fill-in-blank';
const isMatching = (q: Question): q is MatchingType => q.type === 'matching';
const isListenType = (q: Question): q is ListenType => q.type === 'listen-type';
const isSpeakRecord = (q: Question): q is SpeakRecordType => q.type === 'speak-record';

const convertApiQuestion = (q: ApiQuestion): Question => {
  const baseQuestion = {
    id: q.id,
    order: q.order,
    explanation: q.explanation,
    media: q.media,
  };

  if (q.type === 'multiple-choice' && q.question && q.correct_answer && q.options) {
    return {
      ...baseQuestion,
      type: 'multiple-choice',
      question: q.question,
      correct_answer: q.correct_answer,
      options: q.options,
    };
  }

  if (q.type === 'translation' && q.text && q.correct_translation) {
    return {
      ...baseQuestion,
      type: 'translation',
      text: q.text,
      correct_translation: q.correct_translation,
      source_language: q.source_language || 'en',
      target_language: q.target_language || 'es',
      alternatives: [],
    };
  }

  if (q.type === 'fill-in-blank' && q.sentence && q.blanks) {
    return {
      ...baseQuestion,
      type: 'fill-in-blank',
      sentence: q.sentence,
      blanks: q.blanks,
    };
  }

  if (q.type === 'matching' && q.pairs) {
    return {
      ...baseQuestion,
      type: 'matching',
      pairs: q.pairs,
    };
  }

  if (q.type === 'listen-type' && q.audio && q.correct_text) {
    return {
      ...baseQuestion,
      type: 'listen-type',
      audio: q.audio,
      correct_text: q.correct_text,
      language: q.language || 'en',
      alternatives: [],
    };
  }

  if (q.type === 'speak-record' && q.text_to_speak && q.correct_pronunciation && q.example_audio) {
    return {
      ...baseQuestion,
      type: 'speak-record',
      text_to_speak: q.text_to_speak,
      correct_pronunciation: q.correct_pronunciation,
      language: q.language || 'en',
      example_audio: q.example_audio,
    };
  }

  // Default to multiple choice if type is unknown
  return {
    ...baseQuestion,
    type: 'multiple-choice',
    question: q.question || '',
    correct_answer: q.correct_answer || '',
    options: q.options || ['', '', '', ''],
  };
};

export default function ViewQuiz({ params }: { params: { id: string } }) {
  const router = useRouter();
  const [quiz, setQuiz] = useState<Quiz | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadQuiz = async () => {
      const result = await getQuiz(parseInt(params.id));
      if (result.error) {
        setError(result.error);
      } else if (result.data) {
        // Convert API questions to typed questions
        const processedQuestions = result.data.questions.map(convertApiQuestion);
        setQuiz({
          ...result.data,
          questions: processedQuestions
        });
      }
      setLoading(false);
    };

    loadQuiz();
  }, [params.id]);

  const handleDelete = async () => {
    if (!quiz) return;

    const result = await deleteQuiz(quiz.id);
    if (result.error) {
      toast.error('Error', {
        description: result.error,
      });
    } else {
      toast.success('Success', {
        description: 'Quiz deleted successfully',
      });
      router.push(`/admin/lessons/${quiz.lesson_id}/view`);
      router.refresh();
    }
  };

  const handleToggleStatus = async () => {
    if (!quiz) return;

    const result = await toggleQuizStatus(quiz.id);
    if (result.error) {
      toast.error('Error', {
        description: result.error,
      });
    } else {
      setQuiz(prev => prev ? { ...prev, is_published: !prev.is_published } : null);
      toast.success('Success', {
        description: 'Quiz status updated successfully',
      });
    }
  };

  const renderQuestion = (question: Question) => {
    if (isMultipleChoice(question)) {
      return <MultipleChoiceQuestion question={question} isEditing={false} />;
    }
    if (isTranslation(question)) {
      return <TranslationQuestion question={question} isEditing={false} />;
    }
    if (isFillInBlank(question)) {
      return <FillInBlankQuestion question={question} isEditing={false} />;
    }
    if (isMatching(question)) {
      return <MatchingQuestion question={question} isEditing={false} />;
    }
    if (isListenType(question)) {
      return <ListenTypeQuestion question={question} isEditing={false} />;
    }
    if (isSpeakRecord(question)) {
      return <SpeakRecordQuestion question={question} isEditing={false} />;
    }
    return null;
  };

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
      {/* Header */}
      <div className="flex justify-between items-start">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">{quiz.title}</h2>
          <p className="text-muted-foreground">{quiz.description}</p>
        </div>
        <div className="flex gap-2">
          <Button
            variant={quiz.is_published ? "outline" : "default"}
            onClick={handleToggleStatus}
          >
            {quiz.is_published ? "Unpublish" : "Publish"}
          </Button>
          <Link href={`/admin/quizzes/${quiz.id}`}>
            <Button variant="outline">Edit</Button>
          </Link>
          <AlertDialog
            trigger={
              <Button variant="outline" className="text-red-600 hover:text-red-700">
                Delete
              </Button>
            }
            title="Delete Quiz"
            description="Are you sure you want to delete this quiz? This action cannot be undone."
            confirmText="Delete"
            cancelText="Cancel"
            variant="destructive"
            onConfirm={handleDelete}
          />
        </div>
      </div>

      {/* Quiz Settings */}
      <Card className="p-6">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <h3 className="font-semibold mb-2">Settings</h3>
            <dl className="space-y-2">
              <div>
                <dt className="text-sm text-muted-foreground">
                  Passing Score
                </dt>
                <dd>{quiz.passing_score}%</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">
                  Time Limit
                </dt>
                <dd>{quiz.time_limit ? `${quiz.time_limit} minutes` : 'No time limit'}</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">
                  Difficulty Level
                </dt>
                <dd className="capitalize">{quiz.difficulty_level}</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">Status</dt>
                <dd>
                  <span
                    className={`inline-block px-2 py-1 text-xs rounded-full ${
                      quiz.is_published
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700'
                    }`}
                  >
                    {quiz.is_published ? 'Published' : 'Draft'}
                  </span>
                </dd>
              </div>
            </dl>
          </div>
          <div>
            <h3 className="font-semibold mb-2">Questions Overview</h3>
            <dl className="space-y-2">
              <div>
                <dt className="text-sm text-muted-foreground">
                  Total Questions
                </dt>
                <dd>{quiz.questions.length}</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">
                  Question Types
                </dt>
                <dd className="space-y-1">
                  {Object.entries(
                    quiz.questions.reduce((acc, q) => ({
                      ...acc,
                      [q.type]: (acc[q.type] || 0) + 1
                    }), {} as Record<string, number>)
                  ).map(([type, count]) => (
                    <div key={type} className="text-sm">
                      {questionTypeLabels[type as QuestionType] || type}: {count}
                    </div>
                  ))}
                </dd>
              </div>
            </dl>
          </div>
        </div>
      </Card>

      {/* Questions */}
      <div className="space-y-6">
        <h3 className="text-lg font-semibold">Questions</h3>
        {quiz.questions.map((question, index) => (
          <div key={index}>
            <div className="flex items-center gap-2 mb-2">
              <h4 className="text-sm font-medium text-muted-foreground">
                Question {index + 1} - {questionTypeLabels[question.type as QuestionType] || question.type}
              </h4>
            </div>
            {renderQuestion(question)}
          </div>
        ))}
      </div>
    </div>
  );
}