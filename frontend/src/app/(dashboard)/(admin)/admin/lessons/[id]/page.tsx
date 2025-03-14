'use client';

import LessonForm from '@/components/admin/forms/LessonForm';
import { getLesson } from '@/app/_actions/admin/lesson-actions';
import { useEffect, useState } from 'react';

interface Lesson {
  id: number;
  unit_id: number;
  title: string;
  description: string;
  content: string;
  order: number;
  difficulty_level: string;
  estimated_duration: number;
  is_published: boolean;
}

export default function EditLesson({ params }: { params: { id: string } }) {
  const [lesson, setLesson] = useState<Lesson | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadLesson = async () => {
      const result = await getLesson(parseInt(params.id));
      if (result.error) {
        setError(result.error);
      } else {
        setLesson(result.data);
      }
      setLoading(false);
    };

    loadLesson();
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

  if (!lesson) {
    return (
      <div className="text-red-500 p-4 rounded-md bg-red-50 mb-4">
        Lesson not found
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Edit Lesson</h2>
        <p className="text-muted-foreground">
          Update the details of your lesson
        </p>
      </div>

      <LessonForm
        unitId={lesson.unit_id}
        initialData={{
          id: lesson.id,
          title: lesson.title,
          description: lesson.description,
          content: lesson.content,
          order: lesson.order,
          difficulty_level: lesson.difficulty_level,
          estimated_duration: lesson.estimated_duration,
          is_published: lesson.is_published,
        }}
      />
    </div>
  );
}