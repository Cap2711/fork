'use client';

import { createQuiz } from '@/app/_actions/admin/quiz-actions';
import QuizForm from '@/components/admin/forms/QuizForm';
import { QuizFormData } from '@/types/quiz';

interface NewQuizPageProps {
  params: {
    id: string;
  };
}

export default function NewQuizPage({ params }: NewQuizPageProps) {
  const handleSubmit = async (data: QuizFormData) => {
    const result = await createQuiz({
      ...data,
      type: 'lesson_assessment',
      lesson_id: parseInt(params.id),
    });

    return {
      error: result.error || undefined
    };
  };

  const breadcrumbs = [
    { label: 'Lessons', href: '/admin/lessons' },
    { label: `Lesson ${params.id}`, href: `/admin/lessons/${params.id}/view` },
    { label: 'New Quiz', href: '#' },
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">New Lesson Quiz</h1>
          <p className="text-muted-foreground">
            Create an assessment quiz for your lesson
          </p>
        </div>
        <nav className="flex gap-2 text-sm text-muted-foreground">
          {breadcrumbs.map((item, index) => (
            <div key={item.href} className="flex items-center gap-2">
              {index > 0 && <span>/</span>}
              {item.href === '#' ? (
                <span>{item.label}</span>
              ) : (
                <a
                  href={item.href}
                  className="hover:text-foreground hover:underline"
                >
                  {item.label}
                </a>
              )}
            </div>
          ))}
        </nav>
      </div>

      {/* Form */}
      <QuizForm
        lessonId={parseInt(params.id)}
        onSubmit={handleSubmit}
      />
    </div>
  );
}