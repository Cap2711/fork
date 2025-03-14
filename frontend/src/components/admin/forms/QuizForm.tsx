'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { createQuiz, updateQuiz } from '@/app/_actions/admin/quiz-actions';
import { toast } from 'sonner';
import { AlertDialog } from '@/components/admin/AlertDialog';
import { Question, QuestionType } from '@/components/admin/questions/types';
import MultipleChoiceQuestion from '@/components/admin/questions/MultipleChoiceQuestion';
import TranslationQuestion from '@/components/admin/questions/TranslationQuestion';
import FillInBlankQuestion from '@/components/admin/questions/FillInBlankQuestion';
import MatchingQuestion from '@/components/admin/questions/MatchingQuestion';
import ListenTypeQuestion from '@/components/admin/questions/ListenTypeQuestion';
import SpeakRecordQuestion from '@/components/admin/questions/SpeakRecordQuestion';

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

const questionTypes: { type: QuestionType; label: string }[] = [
  { type: 'multiple-choice', label: 'Multiple Choice' },
  { type: 'translation', label: 'Translation' },
  { type: 'fill-in-blank', label: 'Fill in the Blanks' },
  { type: 'matching', label: 'Matching Pairs' },
  { type: 'listen-type', label: 'Listen and Type' },
  { type: 'speak-record', label: 'Speak and Record' },
];

const getEmptyQuestion = (type: QuestionType): Question => {
  const baseQuestion = {
    type,
    order: 0,
    explanation: '',
    difficulty_level: 'normal',
  };

  switch (type) {
    case 'multiple-choice':
      return {
        ...baseQuestion,
        type,
        question: '',
        correct_answer: '',
        options: ['', '', '', ''],
      };
    case 'translation':
      return {
        ...baseQuestion,
        type,
        text: '',
        correct_translation: '',
        alternatives: [],
        source_language: 'en',
        target_language: 'es',
      };
    case 'fill-in-blank':
      return {
        ...baseQuestion,
        type,
        sentence: '',
        blanks: [{
          position: 0,
          correct_answer: '',
          alternatives: [],
        }],
      };
    case 'matching':
      return {
        ...baseQuestion,
        type,
        pairs: [
          { left: '', right: '' },
          { left: '', right: '' },
        ],
      };
    case 'listen-type':
      return {
        ...baseQuestion,
        type,
        audio: {
          type: 'audio',
          url: '',
        },
        correct_text: '',
        alternatives: [],
        language: 'en',
      };
    case 'speak-record':
      return {
        ...baseQuestion,
        type,
        text_to_speak: '',
        correct_pronunciation: '',
        language: 'en',
        example_audio: {
          type: 'audio',
          url: '',
        },
      };
    default:
      throw new Error('Unknown question type');
  }
};

export default function QuizForm({ lessonId, initialData }: QuizFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [showDiscardWarning, setShowDiscardWarning] = useState(false);
  const [showQuestionTypeSelector, setShowQuestionTypeSelector] = useState(false);

  const [formData, setFormData] = useState({
    title: initialData?.title || '',
    description: initialData?.description || '',
    passing_score: initialData?.passing_score || 70,
    time_limit: initialData?.time_limit || 0,
    difficulty_level: initialData?.difficulty_level || 'beginner',
    is_published: initialData?.is_published || false,
    questions: initialData?.questions || [],
  });

  const updateField = (field: string, value: string | number | boolean) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    setHasUnsavedChanges(true);
  };

  const addQuestion = (type: QuestionType) => {
    const newQuestion = getEmptyQuestion(type);
    newQuestion.order = formData.questions.length;
    setFormData(prev => ({
      ...prev,
      questions: [...prev.questions, newQuestion],
    }));
    setHasUnsavedChanges(true);
    setShowQuestionTypeSelector(false);
  };

  const updateQuestion = (index: number, updatedQuestion: Question) => {
    setFormData(prev => ({
      ...prev,
      questions: prev.questions.map((q, i) =>
        i === index ? updatedQuestion : q
      ),
    }));
    setHasUnsavedChanges(true);
  };

  const removeQuestion = (index: number) => {
    setFormData(prev => ({
      ...prev,
      questions: prev.questions.filter((_, i) => i !== index)
        .map((q, i) => ({ ...q, order: i })),
    }));
    setHasUnsavedChanges(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const form = new FormData();
      Object.entries(formData).forEach(([key, value]) => {
        if (key === 'questions') {
          form.append(key, JSON.stringify(value));
        } else {
          form.append(key, value.toString());
        }
      });

      if (lessonId) {
        form.append('lesson_id', lessonId.toString());
      }

      const action = initialData?.id ? updateQuiz : createQuiz;
      const result = await action(initialData?.id || 0, form);

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

  const renderQuestion = (question: Question, index: number) => {
    const commonProps = {
      isEditing: true,
      onUpdate: (updated: Question) => updateQuestion(index, updated),
      onDelete: () => removeQuestion(index),
    };

    switch (question.type) {
      case 'multiple-choice':
        return <MultipleChoiceQuestion key={index} question={question} {...commonProps} />;
      case 'translation':
        return <TranslationQuestion key={index} question={question} {...commonProps} />;
      case 'fill-in-blank':
        return <FillInBlankQuestion key={index} question={question} {...commonProps} />;
      case 'matching':
        return <MatchingQuestion key={index} question={question} {...commonProps} />;
      case 'listen-type':
        return <ListenTypeQuestion key={index} question={question} {...commonProps} />;
      case 'speak-record':
        return <SpeakRecordQuestion key={index} question={question} {...commonProps} />;
      default:
        return null;
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
      <div className="space-y-4">
        <div className="flex justify-between items-center">
          <h3 className="text-lg font-semibold">Questions</h3>
          <Button
            type="button"
            onClick={() => setShowQuestionTypeSelector(true)}
          >
            Add Question
          </Button>
        </div>

        {/* Question Type Selector */}
        {showQuestionTypeSelector && (
          <Card className="p-6">
            <h4 className="font-medium mb-4">Select Question Type</h4>
            <div className="grid grid-cols-2 gap-4">
              {questionTypes.map(({ type, label }) => (
                <Button
                  key={type}
                  type="button"
                  variant="outline"
                  onClick={() => addQuestion(type)}
                  className="h-auto py-4 text-left justify-start"
                >
                  {label}
                </Button>
              ))}
            </div>
          </Card>
        )}

        {/* Questions List */}
        {formData.questions.map((question, index) => (
          <div key={index} className="space-y-2">
            <div className="flex items-center gap-2 mb-2">
              <h4 className="font-medium">
                Question {index + 1} - {questionTypes.find(t => t.type === question.type)?.label}
              </h4>
            </div>
            {renderQuestion(question, index)}
          </div>
        ))}
      </div>

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