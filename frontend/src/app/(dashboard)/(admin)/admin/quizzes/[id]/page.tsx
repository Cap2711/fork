'use client';

import QuizForm from '@/components/admin/forms/QuizForm';
import { getQuiz } from '@/app/_actions/admin/quiz-actions';
import { useEffect, useState } from 'react';

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
}

export default function EditQuiz({ params }: { params: { id: string } }) {
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
          Update the quiz details and questions
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