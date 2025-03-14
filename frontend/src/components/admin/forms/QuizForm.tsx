'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { createQuiz, updateQuiz } from '@/app/_actions/admin/quiz-actions';
import { toast } from 'sonner';
import { AlertDialog } from '@/components/admin/AlertDialog';

interface Question {
  id?: number;
  question: string;
  correct_answer: string;
  options: string[];
  explanation: string;
  order: number;
}

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

export default function QuizForm({ lessonId, initialData }: QuizFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [showDiscardWarning, setShowDiscardWarning] = useState(false);

  const [formData, setFormData] = useState({
    title: initialData?.title || '',
    description: initialData?.description || '',
    passing_score: initialData?.passing_score || 70,
    time_limit: initialData?.time_limit || 0,
    difficulty_level: initialData?.difficulty_level || 'beginner',
    is_published: initialData?.is_published || false,
    questions: initialData?.questions || [
      {
        question: '',
        correct_answer: '',
        options: ['', '', '', ''],
        explanation: '',
        order: 0,
      },
    ],
  });

  const updateField = (field: string, value: string | number | boolean) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    setHasUnsavedChanges(true);
  };

  const updateQuestion = (index: number, field: string, value: string | string[]) => {
    setFormData(prev => ({
      ...prev,
      questions: prev.questions.map((q, i) =>
        i === index ? { ...q, [field]: value } : q
      ),
    }));
    setHasUnsavedChanges(true);
  };

  const addQuestion = () => {
    setFormData(prev => ({
      ...prev,
      questions: [
        ...prev.questions,
        {
          question: '',
          correct_answer: '',
          options: ['', '', '', ''],
          explanation: '',
          order: prev.questions.length,
        },
      ],
    }));
    setHasUnsavedChanges(true);
  };

  const removeQuestion = (index: number) => {
    setFormData(prev => ({
      ...prev,
      questions: prev.questions.filter((_, i) => i !== index),
    }));
    setHasUnsavedChanges(true);
  };

  const updateOption = (questionIndex: number, optionIndex: number, value: string) => {
    setFormData(prev => ({
      ...prev,
      questions: prev.questions.map((q, i) =>
        i === questionIndex
          ? {
              ...q,
              options: q.options.map((opt, j) =>
                j === optionIndex ? value : opt
              ),
            }
          : q
      ),
    }));
    setHasUnsavedChanges(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    const form = new FormData();
    form.append('title', formData.title);
    form.append('description', formData.description);
    form.append('passing_score', formData.passing_score.toString());
    form.append('time_limit', formData.time_limit.toString());
    form.append('difficulty_level', formData.difficulty_level);
    form.append('is_published', formData.is_published.toString());
    form.append('questions', JSON.stringify(formData.questions));
    if (lessonId) {
      form.append('lesson_id', lessonId.toString());
    }

    try {
      if (initialData?.id) {
        const result = await updateQuiz(initialData.id, form);
        if (result.error) {
          toast.error('Error', {
            description: result.error,
          });
        } else {
          toast.success('Success', {
            description: 'Quiz updated successfully',
          });
          setHasUnsavedChanges(false);
          router.refresh();
          router.push(`/admin/lessons/${lessonId}/view`);
        }
      } else {
        const result = await createQuiz(form);
        if (result.error) {
          toast.error('Error', {
            description: result.error,
          });
        } else {
          toast.success('Success', {
            description: 'Quiz created successfully',
          });
          setHasUnsavedChanges(false);
          router.refresh();
          router.push(`/admin/lessons/${lessonId}/view`);
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

  const difficultyLevels = ['beginner', 'intermediate', 'advanced', 'expert'];

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <Card className="p-6">
        <div className="space-y-4">
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

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <label className="text-sm font-medium" htmlFor="passing_score">
                Passing Score (%)
              </label>
              <Input
                id="passing_score"
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
              <label className="text-sm font-medium" htmlFor="time_limit">
                Time Limit (minutes, 0 for no limit)
              </label>
              <Input
                id="time_limit"
                type="number"
                min="0"
                value={formData.time_limit}
                onChange={(e) =>
                  updateField('time_limit', parseInt(e.target.value) || 0)
                }
                required
              />
            </div>

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

      <div className="space-y-4">
        <div className="flex justify-between items-center">
          <h3 className="text-lg font-semibold">Questions</h3>
          <Button type="button" onClick={addQuestion}>
            Add Question
          </Button>
        </div>

        {formData.questions.map((question, qIndex) => (
          <Card key={qIndex} className="p-6">
            <div className="space-y-4">
              <div className="flex justify-between items-start">
                <h4 className="font-medium">Question {qIndex + 1}</h4>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="text-red-600"
                  onClick={() => removeQuestion(qIndex)}
                >
                  Remove
                </Button>
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium">Question Text</label>
                <textarea
                  className="w-full min-h-[100px] px-3 py-2 rounded-md border border-input bg-background"
                  value={question.question}
                  onChange={(e) =>
                    updateQuestion(qIndex, 'question', e.target.value)
                  }
                  required
                />
              </div>

              <div className="space-y-4">
                <label className="text-sm font-medium">Options</label>
                {question.options.map((option, oIndex) => (
                  <div key={oIndex} className="flex gap-2 items-center">
                    <Input
                      value={option}
                      onChange={(e) =>
                        updateOption(qIndex, oIndex, e.target.value)
                      }
                      required
                      placeholder={`Option ${oIndex + 1}`}
                    />
                    <input
                      type="radio"
                      name={`correct_${qIndex}`}
                      checked={question.correct_answer === option}
                      onChange={() =>
                        updateQuestion(qIndex, 'correct_answer', option)
                      }
                      required
                    />
                  </div>
                ))}
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium">Explanation</label>
                <textarea
                  className="w-full min-h-[100px] px-3 py-2 rounded-md border border-input bg-background"
                  value={question.explanation}
                  onChange={(e) =>
                    updateQuestion(qIndex, 'explanation', e.target.value)
                  }
                  placeholder="Explain why this answer is correct..."
                  required
                />
              </div>
            </div>
          </Card>
        ))}
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