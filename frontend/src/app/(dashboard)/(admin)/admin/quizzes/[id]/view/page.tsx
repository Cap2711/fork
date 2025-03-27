'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { getQuiz, deleteQuiz, toggleQuizStatus } from '@/app/_actions/admin/quiz-actions';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { AlertDialog } from '@/components/admin/AlertDialog';
import QuestionWrapper, { getQuestionLabel } from '@/components/admin/questions/QuestionWrapper';
import { Question, Media, QuestionType } from '@/components/admin/questions/types';

interface ApiQuiz {
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

interface RawMedia {
  type: 'image' | 'audio';
  url: string;
  alt?: string;
}

interface RawQuestion {
  id?: number;
  type?: QuestionType;
  question?: string;
  correct_answer?: string;
  options?: string[];
  explanation?: string;
  difficulty_level?: string;
  order?: number;
  statement?: string;
  is_true?: boolean;
  text?: string;
  correct_translation?: string;
  alternatives?: string[];
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
    media?: RawMedia;
  }>;
  text_to_speak?: string;
  correct_pronunciation?: string;
  example_audio?: RawMedia;
  media?: RawMedia[];
}

const transformMedia = (media?: RawMedia): Media => {
  if (!media) return { type: 'audio', url: '', alt: '' };
  return {
    type: media.type,
    url: media.url,
    alt: media.alt || '',
  };
};

// Helper function to ensure all required fields are present
const transformQuestion = (raw: RawQuestion): Question => {
  const baseQuestion = {
    id: raw.id || 0,
    type: raw.type || 'multiple-choice' as const,
    explanation: raw.explanation || '',
    difficulty_level: raw.difficulty_level || 'normal',
    order: raw.order || 0,
    media: raw.media?.map(transformMedia) || [],
  };

  switch (raw.type) {
    case 'multiple-choice':
      return {
        ...baseQuestion,
        type: 'multiple-choice' as const,
        question: raw.question || '',
        correct_answer: raw.correct_answer || '',
        options: raw.options || [],
      };

    case 'true-false':
      return {
        ...baseQuestion,
        type: 'true-false' as const,
        statement: raw.statement || '',
        is_true: raw.is_true ?? false,
      };

    case 'translation':
      return {
        ...baseQuestion,
        type: 'translation' as const,
        text: raw.text || '',
        correct_translation: raw.correct_translation || '',
        alternatives: raw.alternatives || [],
        source_language: raw.source_language || 'en',
        target_language: raw.target_language || 'es',
      };

    case 'fill-in-blank':
      return {
        ...baseQuestion,
        type: 'fill-in-blank' as const,
        sentence: raw.sentence || '',
        blanks: raw.blanks || [{ position: 0, correct_answer: '', alternatives: [] }],
      };

    case 'matching':
      return {
        ...baseQuestion,
        type: 'matching' as const,
        pairs: raw.pairs ? raw.pairs.map(pair => ({
          left: pair.left,
          right: pair.right,
          media: pair.media ? transformMedia(pair.media) : undefined,
        })) : [
          { left: '', right: '' },
          { left: '', right: '' },
        ],
      };

    case 'listen-type':
      return {
        ...baseQuestion,
        type: 'listen-type' as const,
        audio: transformMedia(raw.media?.[0]),
        correct_text: raw.text || '',
        alternatives: raw.alternatives || [],
        language: raw.source_language || 'en',
      };

    case 'speak-record':
      return {
        ...baseQuestion,
        type: 'speak-record' as const,
        text_to_speak: raw.text_to_speak || '',
        correct_pronunciation: raw.correct_pronunciation || '',
        language: raw.source_language || 'en',
        example_audio: raw.example_audio ? transformMedia(raw.example_audio) : { type: 'audio', url: '', alt: '' },
      };

    default:
      return {
        ...baseQuestion,
        type: 'multiple-choice' as const,
        question: '',
        correct_answer: '',
        options: ['', '', '', ''],
      };
  }
};

export default function ViewQuiz({ params }: { params: { id: string } }) {
  const router = useRouter();
  const [quiz, setQuiz] = useState<ApiQuiz | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadQuiz = async () => {
      const result = await getQuiz(parseInt(params.id));
      if (result.error) {
        setError(result.error);
      } else if (result.data) {
        // Transform questions to ensure they have all required fields
        setQuiz({
          ...result.data,
          questions: result.data.questions.map(q => transformQuestion(q as RawQuestion)),
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
          <Button
            variant="outline"
            onClick={() => router.push(`/admin/quizzes/${quiz.id}`)}
          >
            Edit
          </Button>
          <AlertDialog
            trigger={
              <Button variant="outline" className="text-red-600">
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

      {/* Quiz Details */}
      <Card className="p-6">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <h3 className="font-semibold mb-2">Quiz Settings</h3>
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
            <h3 className="font-semibold mb-2">Statistics</h3>
            <dl className="space-y-2">
              <div>
                <dt className="text-sm text-muted-foreground">
                  Total Questions
                </dt>
                <dd>{quiz.questions.length}</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">
                  Last Updated
                </dt>
                <dd>{new Date(quiz.updated_at).toLocaleDateString()}</dd>
              </div>
            </dl>
          </div>
        </div>
      </Card>

      {/* Questions */}
      <div className="space-y-4">
        <h3 className="text-lg font-semibold">Questions</h3>
        {quiz.questions.map((question, index) => (
          <div key={index}>
            <h4 className="font-medium mb-2">
              Question {index + 1} - {getQuestionLabel(question.type)}
            </h4>
            <QuestionWrapper
              question={question}
              isEditing={false}
            />
          </div>
        ))}
      </div>
    </div>
  );
}