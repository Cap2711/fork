'use client';

import { createSection, prepareSectionFormData } from '@/app/_actions/admin/section-actions';
import SectionForm from '@/components/admin/forms/SectionForm';
import { SectionFormData } from '@/types/section';

interface NewSectionPageProps {
  params: {
    id: string;
  };
}

export default function NewSectionPage({ params }: NewSectionPageProps) {
  const handleSubmit = async (data: Omit<SectionFormData, 'lesson_id'>) => {
    const formData = prepareSectionFormData({
      ...data,
      lesson_id: parseInt(params.id),
    });
    return createSection(formData);
  };

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">New Section</h2>
        <p className="text-muted-foreground">
          Add a new section to your lesson
        </p>
      </div>

      <SectionForm
        lessonId={parseInt(params.id)}
        onSubmit={handleSubmit}
      />
    </div>
  );
}