'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { AlertDialog } from '@/components/admin/AlertDialog';
import MediaUploader from '@/components/admin/questions/MediaUploader';
import {
  SectionFormData,
  SectionType,
  DEFAULT_SECTION_VALUES,
  MediaItem,
  TheoryContent,
  PracticeContent,
  MiniQuizContent,
  SectionContent,
} from '@/types/section';
import { Editor } from '@/components/ui/editor';
import QuestionFormSection from '@/components/admin/questions/QuestionFormSection';
import { Question } from '@/components/admin/questions/types';

interface SectionFormProps {
  lessonId: number;
  initialData?: SectionFormData;
  onSubmit: (data: SectionFormData) => Promise<{ error?: string }>;
}

export default function SectionForm({ lessonId, initialData, onSubmit }: SectionFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [showDiscardWarning, setShowDiscardWarning] = useState(false);

  const [formData, setFormData] = useState<SectionFormData>(
    initialData || { ...DEFAULT_SECTION_VALUES }
  );

  const updateField = <K extends keyof SectionFormData>(
    field: K,
    value: SectionFormData[K]
  ) => {
    if (field === 'type') {
      const newType = value as SectionType;
      let newContent: SectionContent;

      switch (newType) {
        case 'theory':
          newContent = { text: '', media: [], examples: [] };
          break;
        case 'practice':
          newContent = {
            instructions: '',
            questions: [],
            settings: { showHints: true, showFeedback: true },
          };
          break;
        case 'mini_quiz':
          newContent = {
            questions: [],
            settings: { passingScore: 70, showExplanations: true },
          };
          break;
        default:
          newContent = { text: '', media: [], examples: [] };
      }

      setFormData(prev => ({
        ...prev,
        type: newType,
        content: newContent,
      }));
    } else {
      setFormData(prev => ({ ...prev, [field]: value }));
    }
    setHasUnsavedChanges(true);
  };

  const updateTheoryContent = (content: Partial<TheoryContent>) => {
    if (formData.type === 'theory') {
      setFormData(prev => ({
        ...prev,
        content: {
          ...(prev.content as TheoryContent),
          ...content,
        },
      }));
      setHasUnsavedChanges(true);
    }
  };

  const updatePracticeContent = (content: Partial<PracticeContent>) => {
    if (formData.type === 'practice') {
      setFormData(prev => ({
        ...prev,
        content: {
          ...(prev.content as PracticeContent),
          ...content,
        },
      }));
      setHasUnsavedChanges(true);
    }
  };

  const updateMiniQuizContent = (content: Partial<MiniQuizContent>) => {
    if (formData.type === 'mini_quiz') {
      setFormData(prev => ({
        ...prev,
        content: {
          ...(prev.content as MiniQuizContent),
          ...content,
        },
      }));
      setHasUnsavedChanges(true);
    }
  };

  const handleMediaUpload = (newMedia: MediaItem[]) => {
    if (formData.type === 'theory') {
      const content = formData.content as TheoryContent;
      updateTheoryContent({
        media: [...(content.media || []), ...newMedia],
      });
    }
  };

  const handleQuestionUpdate = (questions: Question[]) => {
    if (formData.type === 'practice') {
      updatePracticeContent({ questions });
    } else if (formData.type === 'mini_quiz') {
      updateMiniQuizContent({ questions });
    }
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
          description: `Section ${initialData ? 'updated' : 'created'} successfully`,
        });
        setHasUnsavedChanges(false);
        router.push(`/admin/lessons/${lessonId}/view`);
        router.refresh();
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

  const renderTheoryContent = () => {
    const content = formData.content as TheoryContent;
    return (
      <div className="space-y-4">
        <div>
          <label className="text-sm font-medium mb-2 block">Content</label>
          <Editor
            content={content.text}
            onChange={(text: string) => updateTheoryContent({ text })}
          />
        </div>
        
        <div>
          <label className="text-sm font-medium mb-2 block">Media</label>
          <MediaUploader
            files={content.media}
            onUpload={handleMediaUpload}
            maxFiles={5}
          />
        </div>

        <div className="space-y-2">
          <label className="text-sm font-medium">Examples</label>
          {content.examples?.map((example, i) => (
            <div key={i} className="flex gap-2">
              <Input
                value={example.text}
                onChange={(e) => {
                  const newExamples = [...(content.examples || [])];
                  newExamples[i] = {
                    ...example,
                    text: e.target.value,
                  };
                  updateTheoryContent({ examples: newExamples });
                }}
                placeholder="Example text"
              />
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  const newExamples = content.examples?.filter(
                    (_, index) => index !== i
                  );
                  updateTheoryContent({ examples: newExamples });
                }}
              >
                Remove
              </Button>
            </div>
          ))}
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              const newExamples = [
                ...(content.examples || []),
                { text: '', explanation: '' },
              ];
              updateTheoryContent({ examples: newExamples });
            }}
          >
            Add Example
          </Button>
        </div>
      </div>
    );
  };

  const renderPracticeContent = () => {
    const content = formData.content as PracticeContent;
    return (
      <div className="space-y-4">
        <div>
          <label className="text-sm font-medium mb-2 block">Instructions</label>
          <Editor
            content={content.instructions}
            onChange={(instructions: string) =>
              updatePracticeContent({ instructions })
            }
          />
        </div>

        <QuestionFormSection
          questions={content.questions}
          onQuestionsChange={handleQuestionUpdate}
        />
      </div>
    );
  };

  const renderMiniQuizContent = () => {
    const content = formData.content as MiniQuizContent;
    return (
      <div className="space-y-4">
        <QuestionFormSection
          questions={content.questions}
          onQuestionsChange={handleQuestionUpdate}
        />
      </div>
    );
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <Card className="p-6">
        <div className="space-y-4">
          {/* Section Type */}
          <div className="space-y-2">
            <label className="text-sm font-medium">Section Type</label>
            <select
              className="w-full px-3 py-2 rounded-md border border-input bg-background"
              value={formData.type}
              onChange={(e) => updateField('type', e.target.value as SectionType)}
              required
            >
              <option value="theory">Theory / Content</option>
              <option value="practice">Practice Exercise</option>
              <option value="mini_quiz">Mini Quiz</option>
            </select>
          </div>

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

          {/* Content by Type */}
          {formData.type === 'theory' && renderTheoryContent()}
          {formData.type === 'practice' && renderPracticeContent()}
          {formData.type === 'mini_quiz' && renderMiniQuizContent()}

          {/* Settings */}
          <div className="grid grid-cols-2 gap-4">
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

            <div className="space-y-2">
              <label className="text-sm font-medium">
                Estimated Time (minutes)
              </label>
              <Input
                type="number"
                min="1"
                value={formData.estimated_time}
                onChange={(e) =>
                  updateField('estimated_time', parseInt(e.target.value) || 1)
                }
                required
              />
            </div>
          </div>

          {/* Progress Requirements */}
          <div className="space-y-4">
            <div className="flex items-center space-x-2">
              <input
                type="checkbox"
                id="requires_previous"
                checked={formData.requires_previous}
                onChange={(e) =>
                  updateField('requires_previous', e.target.checked)
                }
                className="rounded border-gray-300"
              />
              <label className="text-sm font-medium" htmlFor="requires_previous">
                Require previous section completion
              </label>
            </div>

            <div className="flex items-center space-x-2">
              <input
                type="checkbox"
                id="allow_retry"
                checked={formData.allow_retry}
                onChange={(e) => updateField('allow_retry', e.target.checked)}
                className="rounded border-gray-300"
              />
              <label className="text-sm font-medium" htmlFor="allow_retry">
                Allow multiple attempts
              </label>
            </div>

            <div className="flex items-center space-x-2">
              <input
                type="checkbox"
                id="show_solution"
                checked={formData.show_solution}
                onChange={(e) => updateField('show_solution', e.target.checked)}
                className="rounded border-gray-300"
              />
              <label className="text-sm font-medium" htmlFor="show_solution">
                Show solution after completion
              </label>
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
            ? 'Update Section'
            : 'Create Section'}
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