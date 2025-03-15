'use client';

import { createQuiz } from '@/app/_actions/admin/quiz-actions';
import QuizForm from '@/components/admin/forms/QuizForm';
import { QuizFormData } from '@/types/quiz';

interface NewSectionQuizPageProps {
  params: {
    id: string;
    sectionId: string;
  };
}

export default function NewSectionQuizPage({ params }: NewSectionQuizPageProps) {
  const handleSubmit = async (data: QuizFormData) => {
    const result = await createQuiz({
      ...data,
      type: 'section_practice',
      lesson_id: parseInt(params.id),
      section_id: parseInt(params.sectionId),
    });

    return {
      error: result.error || undefined
    };
  };

  const breadcrumbs = [
    { label: 'Lessons', href: '/admin/lessons' },
    { label: `Lesson ${params.id}`, href: `/admin/lessons/${params.id}/view` },
    { 
      label: `Section ${params.sectionId}`, 
      href: `/admin/lessons/${params.id}/sections/${params.sectionId}/view` 
    },
    { label: 'New Practice Quiz', href: '#' },
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">New Practice Quiz</h1>
          <p className="text-muted-foreground">
            Create a practice quiz for this section
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
        sectionId={parseInt(params.sectionId)}
        onSubmit={handleSubmit}
      />
    </div>
  );
}