import QuizForm from '@/components/admin/forms/QuizForm';

export default function NewQuiz({ params }: { params: { id: string } }) {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Create Quiz</h2>
        <p className="text-muted-foreground">
          Create a new quiz for this lesson
        </p>
      </div>

      <QuizForm lessonId={parseInt(params.id)} />
    </div>
  );
}