'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { createQuiz, updateQuiz } from '@/app/_actions/admin/quiz-actions';
import { toast } from 'sonner';
import { AlertDialog } from '@/components/admin/AlertDialog';
import { Question } from '@/components/admin/questions/types';
import QuestionFormSection from '@/components/admin/questions/QuestionFormSection';

interface QuizFormProps {
  lessonId?: number;
  initialData?: {
    id?: number;
    title: string;
    description: string;
    passing_score: number;
    time_limit: number | null;
    difficulty_level: string;
    is_published: boolean;
    questions: Question[];
  };
}

interface QuizSubmitData {
  title: string;
  description: string;
  passing_score: number;
  time_limit: number | null;
  difficulty_level: string;
  is_published: boolean;
  questions: Question[];
  lesson_id?: number;
}

export default function QuizForm({ lessonId, initialData }: QuizFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [showDiscardWarning, setShowDiscardWarning] = useState(false);

  const [formData, setFormData] = useState<QuizSubmitData>({
    title: initialData?.title || '',
    description: initialData?.description || '',
    passing_score: initialData?.passing_score || 70,
    time_limit: initialData?.time_limit || 0,
    difficulty_level: initialData?.difficulty_level || 'beginner',
    is_published: initialData?.is_published || false,
    questions: initialData?.questions || [],
    lesson_id: lessonId,
  });

  const updateField = (field: string, value: string | number | boolean | Question[]) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    setHasUnsavedChanges(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const result = initialData?.id 
        ? await updateQuiz(initialData.id, formData)
        : await createQuiz(formData);

      if (result.error) {
        toast.error('Error', {
          description: result.error,
        });
      } else {
        toast.success('Success', {
          description: `Quiz ${initialData?.id ? 'updated' : 'created'} successfully`,
        });
        setHasUnsavedChanges(false);
        router.refresh();
        router.push(`/admin/lessons/${lessonId}/view`);
      }
    } catch {
      toast.error('Error', {
        description: 'An unexpected error occurred',
      });
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    if (hasUnsavedChanges) {
      setShowDiscardWarning(true);
    } else {
      router.back();
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Quiz Settings */}
      <Card className="p-6">
        <div className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium">Title</label>
            <Input
              value={formData.title}
              onChange={(e) => updateField('title', e.target.value)}
              required
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Description</label>
            <textarea
              className="w-full min-h-[100px] px-3 py-2 rounded-md border border-input bg-background"
              value={formData.description}
              onChange={(e) => updateField('description', e.target.value)}
              required
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <label className="text-sm font-medium">
                Passing Score (%)
              </label>
              <Input
                type="number"
                min="0"
                max="100"
                value={formData.passing_score}
                onChange={(e) =>
                  updateField('passing_score', parseInt(e.target.value) || 0)
                }
                required
              />
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium">
                Time Limit (minutes, 0 for no limit)
              </label>
              <Input
                type="number"
                min="0"
                value={formData.time_limit}
                onChange={(e) =>
                  updateField('time_limit', parseInt(e.target.value) || 0)
                }
                required
              />
            </div>
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Difficulty Level</label>
            <select
              className="w-full px-3 py-2 rounded-md border border-input bg-background"
              value={formData.difficulty_level}
              onChange={(e) => updateField('difficulty_level', e.target.value)}
              required
            >
              <option value="beginner">Beginner</option>
              <option value="intermediate">Intermediate</option>
              <option value="advanced">Advanced</option>
              <option value="expert">Expert</option>
            </select>
          </div>

          <div className="flex items-center space-x-2">
            <input
              type="checkbox"
              id="is_published"
              checked={formData.is_published}
              onChange={(e) => updateField('is_published', e.target.checked)}
              className="rounded border-gray-300"
            />
            <label className="text-sm font-medium" htmlFor="is_published">
              Publish immediately
            </label>
          </div>
        </div>
      </Card>

      {/* Questions */}
      <QuestionFormSection 
        questions={formData.questions}
        onQuestionsChange={(questions) => updateField('questions', questions)}
      />

      {/* Actions */}
      <div className="flex justify-end space-x-2">
        <Button
          type="button"
          variant="outline"
          onClick={handleCancel}
          disabled={loading}
        >
          Cancel
        </Button>
        <Button type="submit" disabled={loading}>
          {loading
            ? 'Saving...'
            : initialData?.id
            ? 'Update Quiz'
            : 'Create Quiz'}
        </Button>
      </div>

      {showDiscardWarning && (
        <AlertDialog
          trigger={<></>}
          title="Discard Changes"
          description="You have unsaved changes. Are you sure you want to discard them?"
          confirmText="Discard"
          cancelText="Continue Editing"
          variant="destructive"
          onConfirm={() => router.back()}
        />
      )}
    </form>
  );
}