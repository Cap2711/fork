'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { getLesson } from '@/app/_actions/admin/lesson-actions';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { AlertDialog } from '@/components/admin/AlertDialog';
import { useRouter } from 'next/navigation';
import { deleteLesson, toggleLessonStatus } from '@/app/_actions/admin/lesson-actions';
import { toast } from 'sonner';

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
  created_at: string;
  updated_at: string;
  has_quiz: boolean;
  has_exercise: boolean;
  has_vocabulary: boolean;
}

export default function ViewLesson({ params }: { params: { id: string } }) {
  const router = useRouter();
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

  const handleDelete = async () => {
    if (!lesson) return;

    const result = await deleteLesson(lesson.id);
    if (result.error) {
      toast.error('Error', {
        description: result.error,
      });
    } else {
      toast.success('Success', {
        description: 'Lesson deleted successfully',
      });
      router.push(`/admin/units/${lesson.unit_id}/view`);
      router.refresh();
    }
  };

  const handleToggleStatus = async () => {
    if (!lesson) return;

    const result = await toggleLessonStatus(lesson.id);
    if (result.error) {
      toast.error('Error', {
        description: result.error,
      });
    } else {
      setLesson(prev => prev ? { ...prev, is_published: !prev.is_published } : null);
      toast.success('Success', {
        description: 'Lesson status updated successfully',
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

  if (!lesson) {
    return (
      <div className="text-red-500 p-4 rounded-md bg-red-50 mb-4">
        Lesson not found
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-start">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">{lesson.title}</h2>
          <p className="text-muted-foreground">{lesson.description}</p>
        </div>
        <div className="flex gap-2">
          <Button
            variant={lesson.is_published ? "outline" : "default"}
            onClick={handleToggleStatus}
          >
            {lesson.is_published ? "Unpublish" : "Publish"}
          </Button>
          <Link href={`/admin/lessons/${lesson.id}`}>
            <Button variant="outline">Edit</Button>
          </Link>
          <AlertDialog
            trigger={
              <Button variant="outline" className="text-red-600 hover:text-red-700">
                Delete
              </Button>
            }
            title="Delete Lesson"
            description="Are you sure you want to delete this lesson? This action cannot be undone and will remove all associated content."
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
            <h3 className="font-semibold mb-2">Details</h3>
            <dl className="space-y-2">
              <div>
                <dt className="text-sm text-muted-foreground">Order</dt>
                <dd>{lesson.order}</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">
                  Difficulty Level
                </dt>
                <dd className="capitalize">{lesson.difficulty_level}</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">
                  Estimated Duration
                </dt>
                <dd>{lesson.estimated_duration} hours</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">Status</dt>
                <dd>
                  <span
                    className={`inline-block px-2 py-1 text-xs rounded-full ${
                      lesson.is_published
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700'
                    }`}
                  >
                    {lesson.is_published ? 'Published' : 'Draft'}
                  </span>
                </dd>
              </div>
            </dl>
          </div>
          <div>
            <h3 className="font-semibold mb-2">Associated Content</h3>
            <ul className="space-y-2">
              <li className="flex items-center gap-2">
                <span className={`w-2 h-2 rounded-full ${lesson.has_quiz ? 'bg-green-500' : 'bg-gray-300'}`} />
                <span>Quiz</span>
                {lesson.has_quiz ? (
                  <Link href={`/admin/lessons/${lesson.id}/quiz`}>
                    <Button variant="outline" size="sm">Manage</Button>
                  </Link>
                ) : (
                  <Link href={`/admin/lessons/${lesson.id}/quiz/new`}>
                    <Button variant="outline" size="sm">Add Quiz</Button>
                  </Link>
                )}
              </li>
              <li className="flex items-center gap-2">
                <span className={`w-2 h-2 rounded-full ${lesson.has_exercise ? 'bg-green-500' : 'bg-gray-300'}`} />
                <span>Exercise</span>
                {lesson.has_exercise ? (
                  <Link href={`/admin/lessons/${lesson.id}/exercise`}>
                    <Button variant="outline" size="sm">Manage</Button>
                  </Link>
                ) : (
                  <Link href={`/admin/lessons/${lesson.id}/exercise/new`}>
                    <Button variant="outline" size="sm">Add Exercise</Button>
                  </Link>
                )}
              </li>
              <li className="flex items-center gap-2">
                <span className={`w-2 h-2 rounded-full ${lesson.has_vocabulary ? 'bg-green-500' : 'bg-gray-300'}`} />
                <span>Vocabulary</span>
                {lesson.has_vocabulary ? (
                  <Link href={`/admin/lessons/${lesson.id}/vocabulary`}>
                    <Button variant="outline" size="sm">Manage</Button>
                  </Link>
                ) : (
                  <Link href={`/admin/lessons/${lesson.id}/vocabulary/new`}>
                    <Button variant="outline" size="sm">Add Vocabulary</Button>
                  </Link>
                )}
              </li>
            </ul>
          </div>
        </div>
      </Card>

      {/* Content */}
      <Card className="p-6">
        <h3 className="font-semibold mb-4">Lesson Content</h3>
        <div className="prose max-w-none">
          {lesson.content}
        </div>
      </Card>
    </div>
  );
}