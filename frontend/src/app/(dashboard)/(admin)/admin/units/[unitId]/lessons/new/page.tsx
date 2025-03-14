import LessonForm from '@/components/admin/forms/LessonForm';

export default function NewLesson({ params }: { params: { unitId: string } }) {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Create Lesson</h2>
        <p className="text-muted-foreground">
          Add a new lesson to the unit
        </p>
      </div>

      <LessonForm unitId={parseInt(params.unitId)} />
    </div>
  );
}