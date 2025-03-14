'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { createLesson, updateLesson } from '@/app/_actions/admin/lesson-actions';
import { AlertDialog } from '@/components/admin/AlertDialog';

interface LessonFormProps {
  unitId?: number;
  initialData?: {
    id?: number;
    title: string;
    description: string;
    content: string;
    order: number;
    difficulty_level: string;
    estimated_duration: number;
    is_published: boolean;
  };
  onSuccess?: () => void;
}

export default function LessonForm({ unitId, initialData, onSuccess }: LessonFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [showDiscardWarning, setShowDiscardWarning] = useState(false);

  const [formData, setFormData] = useState({
    title: initialData?.title || '',
    description: initialData?.description || '',
    content: initialData?.content || '',
    order: initialData?.order || 0,
    difficulty_level: initialData?.difficulty_level || 'beginner',
    estimated_duration: initialData?.estimated_duration || 0,
    is_published: initialData?.is_published || false,
  });

  const updateField = (field: string, value: string | number | boolean) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    setHasUnsavedChanges(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    const form = new FormData();
    form.append('title', formData.title);
    form.append('description', formData.description);
    form.append('content', formData.content);
    form.append('order', formData.order.toString());
    form.append('difficulty_level', formData.difficulty_level);
    form.append('estimated_duration', formData.estimated_duration.toString());
    form.append('is_published', formData.is_published.toString());

    try {
      if (initialData?.id) {
        const result = await updateLesson(initialData.id, form);
        if (result.error) {
          setError(result.error);
        } else {
          setHasUnsavedChanges(false);
          onSuccess?.();
          router.push(`/admin/units/${unitId}/view`);
          router.refresh();
        }
      } else if (unitId) {
        const result = await createLesson(unitId, form);
        if (result.error) {
          setError(result.error);
        } else {
          setHasUnsavedChanges(false);
          onSuccess?.();
          router.push(`/admin/units/${unitId}/view`);
          router.refresh();
        }
      }
    } catch {
      setError('An unexpected error occurred');
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

  const difficultyLevels = ['beginner', 'intermediate', 'advanced', 'expert'];

  return (
    <form onSubmit={handleSubmit}>
      <Card className="p-6 space-y-4">
        {error && (
          <div className="text-red-500 p-4 rounded-md bg-red-50 mb-4">
            {error}
          </div>
        )}

        <div className="space-y-2">
          <label className="text-sm font-medium" htmlFor="title">
            Title
          </label>
          <Input
            id="title"
            value={formData.title}
            onChange={(e) => updateField('title', e.target.value)}
            required
          />
        </div>

        <div className="space-y-2">
          <label className="text-sm font-medium" htmlFor="description">
            Description
          </label>
          <textarea
            id="description"
            className="w-full min-h-[100px] px-3 py-2 rounded-md border border-input bg-background"
            value={formData.description}
            onChange={(e) => updateField('description', e.target.value)}
            required
          />
        </div>

        <div className="space-y-2">
          <label className="text-sm font-medium" htmlFor="content">
            Content
          </label>
          <textarea
            id="content"
            className="w-full min-h-[200px] px-3 py-2 rounded-md border border-input bg-background"
            value={formData.content}
            onChange={(e) => updateField('content', e.target.value)}
            required
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="difficulty_level">
              Difficulty Level
            </label>
            <select
              id="difficulty_level"
              className="w-full px-3 py-2 rounded-md border border-input bg-background"
              value={formData.difficulty_level}
              onChange={(e) => updateField('difficulty_level', e.target.value)}
              required
            >
              {difficultyLevels.map((level) => (
                <option key={level} value={level}>
                  {level.charAt(0).toUpperCase() + level.slice(1)}
                </option>
              ))}
            </select>
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="order">
              Order
            </label>
            <Input
              id="order"
              type="number"
              min="0"
              value={formData.order}
              onChange={(e) => updateField('order', parseInt(e.target.value) || 0)}
              required
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="estimated_duration">
              Estimated Duration (hours)
            </label>
            <Input
              id="estimated_duration"
              type="number"
              min="0"
              step="0.5"
              value={formData.estimated_duration}
              onChange={(e) =>
                updateField('estimated_duration', parseFloat(e.target.value) || 0)
              }
              required
            />
          </div>
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
              ? 'Update Lesson'
              : 'Create Lesson'}
          </Button>
        </div>
      </Card>

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