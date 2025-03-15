'use client';

import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { QuestionFormSection } from '@/components/admin/questions/QuestionFormSection';
import { Question } from '@/components/admin/questions/types';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { AlertDialog } from '@/components/admin/AlertDialog';
import { QuizFormData, QuizType, DEFAULT_QUIZ_VALUES, DEFAULT_SECTION_QUIZ_VALUES } from '@/types/quiz';

interface QuizFormProps {
  lessonId: number;
  sectionId?: number;
  initialData?: QuizFormData;
  onSubmit: (data: QuizFormData) => Promise<{ error?: string }>;
}

export default function QuizForm({
  lessonId,
  sectionId,
  initialData,
  onSubmit,
}: QuizFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [showDiscardWarning, setShowDiscardWarning] = useState(false);

  const [formData, setFormData] = useState<QuizFormData>(() => {
    if (initialData) return initialData;

    // Set defaults based on whether this is a section quiz or lesson quiz
    const baseValues = { ...DEFAULT_QUIZ_VALUES, lesson_id: lessonId };
    if (sectionId) {
      return {
        ...baseValues,
        ...DEFAULT_SECTION_QUIZ_VALUES,
        section_id: sectionId,
      };
    }
    return baseValues;
  });

  const updateField = <K extends keyof QuizFormData>(
    field: K,
    value: QuizFormData[K]
  ) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    setHasUnsavedChanges(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const result = await onSubmit(formData);

      if (result.error) {
        toast.error('Error', {
          description: result.error,
        });
      } else {
        toast.success('Success', {
          description: `${formData.type === 'section_practice' ? 'Practice Quiz' : 'Lesson Quiz'} ${
            initialData ? 'updated' : 'created'
          } successfully`,
        });
        setHasUnsavedChanges(false);
        router.refresh();
        if (formData.type === 'lesson_assessment') {
          router.push(`/admin/lessons/${lessonId}/view`);
        } else {
          router.push(`/admin/lessons/${lessonId}/sections/${sectionId}/view`);
        }
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

  const handleQuestionsChange = (questions: Question[]) => {
    updateField('questions', questions);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <Card className="p-6">
        <div className="space-y-4">
          {/* Basic Information */}
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

          {/* Quiz Settings */}
          <div className="space-y-4">
            {/* Common Settings */}
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <label className="text-sm font-medium">Passing Score (%)</label>
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
                <label className="text-sm font-medium">Time Limit (minutes)</label>
                <Input
                  type="number"
                  min="0"
                  value={formData.time_limit || ''}
                  onChange={(e) =>
                    updateField(
                      'time_limit',
                      e.target.value ? parseInt(e.target.value) : undefined
                    )
                  }
                  placeholder="No limit"
                />
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium">XP Reward</label>
                <Input
                  type="number"
                  min="0"
                  value={formData.xp_reward}
                  onChange={(e) =>
                    updateField('xp_reward', parseInt(e.target.value) || 0)
                  }
                  required
                />
              </div>
            </div>

            {/* Quiz Settings */}
            <div className="space-y-4 border-t pt-4">
              <h3 className="font-medium">Quiz Settings</h3>
              <div className="space-y-2">
                {/* Common settings */}
                <div className="flex items-center space-x-2">
                  <input
                    type="checkbox"
                    id="show_feedback"
                    checked={formData.settings.show_feedback}
                    onChange={(e) =>
                      updateField('settings', {
                        ...formData.settings,
                        show_feedback: e.target.checked,
                      })
                    }
                    className="rounded border-gray-300"
                  />
                  <label className="text-sm" htmlFor="show_feedback">
                    Show feedback after each question
                  </label>
                </div>

                <div className="flex items-center space-x-2">
                  <input
                    type="checkbox"
                    id="allow_retry"
                    checked={formData.settings.allow_retry}
                    onChange={(e) =>
                      updateField('settings', {
                        ...formData.settings,
                        allow_retry: e.target.checked,
                      })
                    }
                    className="rounded border-gray-300"
                  />
                  <label className="text-sm" htmlFor="allow_retry">
                    Allow multiple attempts
                  </label>
                </div>

                <div className="flex items-center space-x-2">
                  <input
                    type="checkbox"
                    id="shuffle_questions"
                    checked={formData.settings.shuffle_questions}
                    onChange={(e) =>
                      updateField('settings', {
                        ...formData.settings,
                        shuffle_questions: e.target.checked,
                      })
                    }
                    className="rounded border-gray-300"
                  />
                  <label className="text-sm" htmlFor="shuffle_questions">
                    Shuffle questions
                  </label>
                </div>

                {/* Lesson assessment specific settings */}
                {formData.type === 'lesson_assessment' && (
                  <>
                    <div className="flex items-center space-x-2">
                      <input
                        type="checkbox"
                        id="require_passing_grade"
                        checked={formData.settings.require_passing_grade}
                        onChange={(e) =>
                          updateField('settings', {
                            ...formData.settings,
                            require_passing_grade: e.target.checked,
                          })
                        }
                        className="rounded border-gray-300"
                      />
                      <label className="text-sm" htmlFor="require_passing_grade">
                        Require passing grade to complete lesson
                      </label>
                    </div>

                    <div className="flex items-center space-x-2">
                      <input
                        type="checkbox"
                        id="unlock_next_lesson"
                        checked={formData.settings.unlock_next_lesson}
                        onChange={(e) =>
                          updateField('settings', {
                            ...formData.settings,
                            unlock_next_lesson: e.target.checked,
                          })
                        }
                        className="rounded border-gray-300"
                      />
                      <label className="text-sm" htmlFor="unlock_next_lesson">
                        Unlock next lesson upon completion
                      </label>
                    </div>
                  </>
                )}

                {/* Section practice specific settings */}
                {formData.type === 'section_practice' && (
                  <>
                    <div className="flex items-center space-x-2">
                      <input
                        type="checkbox"
                        id="hints_enabled"
                        checked={formData.settings.hints_enabled}
                        onChange={(e) =>
                          updateField('settings', {
                            ...formData.settings,
                            hints_enabled: e.target.checked,
                          })
                        }
                        className="rounded border-gray-300"
                      />
                      <label className="text-sm" htmlFor="hints_enabled">
                        Enable hints
                      </label>
                    </div>

                    <div className="flex items-center space-x-2">
                      <input
                        type="checkbox"
                        id="practice_mode"
                        checked={formData.settings.practice_mode}
                        onChange={(e) =>
                          updateField('settings', {
                            ...formData.settings,
                            practice_mode: e.target.checked,
                          })
                        }
                        className="rounded border-gray-300"
                      />
                      <label className="text-sm" htmlFor="practice_mode">
                        Practice mode (no penalties)
                      </label>
                    </div>
                  </>
                )}
              </div>
            </div>
          </div>

          {/* Questions */}
          <div className="space-y-4 border-t pt-4">
            <h3 className="font-medium">Questions</h3>
            <QuestionFormSection
              questions={formData.questions}
              onQuestionsChange={handleQuestionsChange}
            />
          </div>
        </div>
      </Card>

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
            : initialData
            ? `Update ${formData.type === 'section_practice' ? 'Practice Quiz' : 'Lesson Quiz'}`
            : `Create ${formData.type === 'section_practice' ? 'Practice Quiz' : 'Lesson Quiz'}`}
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
          onCancel={() => setShowDiscardWarning(false)}
        />
      )}
    </form>
  );
}