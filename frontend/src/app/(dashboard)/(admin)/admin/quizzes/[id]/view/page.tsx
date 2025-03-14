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

interface Quiz {
  id: number;
  lesson_id: number;
  title: string;
  description: string;
  passing_score: number;
  time_limit: number | null;
  difficulty_level: string;
  is_published: boolean;
  questions: Array<{
    id?: number;
    question: string;
    correct_answer: string;
    options: string[];
    explanation: string;
    order: number;
  }>;
  created_at: string;
  updated_at: string;
}

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
      } else {
        setQuiz(result.data);
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

      {/* Details */}
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
          <Card key={index} className="p-6">
            <div className="space-y-4">
              <div>
                <h4 className="font-medium mb-2">Question {index + 1}</h4>
                <p>{question.question}</p>
              </div>
              
              <div className="space-y-2">
                <h5 className="text-sm font-medium text-muted-foreground">Options</h5>
                <ul className="space-y-1">
                  {question.options.map((option, optionIndex) => (
                    <li
                      key={optionIndex}
                      className={`flex items-center gap-2 p-2 rounded ${
                        option === question.correct_answer
                          ? 'bg-green-50 text-green-700'
                          : ''
                      }`}
                    >
                      {option === question.correct_answer && (
                        <svg
                          className="w-4 h-4 text-green-500"
                          fill="none"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth="2"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                        >
                          <path d="M5 13l4 4L19 7" />
                        </svg>
                      )}
                      {option}
                    </li>
                  ))}
                </ul>
              </div>

              {question.explanation && (
                <div className="bg-blue-50 p-4 rounded-md">
                  <h5 className="text-sm font-medium text-blue-700 mb-1">
                    Explanation
                  </h5>
                  <p className="text-blue-600">{question.explanation}</p>
                </div>
              )}
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}