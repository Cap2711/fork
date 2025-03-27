import { Question } from '@/components/admin/questions/types';

export type QuizType = 'lesson_assessment' | 'section_practice';

export interface Quiz {
  id: number;
  lesson_id: number;
  section_id?: number; // Only for section practice quizzes
  title: string;
  description: string;
  type: QuizType;
  questions: Question[];
  passing_score: number;
  time_limit?: number; // in minutes
  xp_reward: number;
  order: number;
  is_published: boolean;
  settings: QuizSettings;
  created_at: string;
  updated_at: string;
}

export interface QuizSettings {
  // Common settings
  show_feedback: boolean;
  allow_retry: boolean;
  shuffle_questions: boolean;
  show_progress: boolean;

  // Lesson assessment specific
  require_passing_grade: boolean;
  unlock_next_lesson: boolean;
  certificate_on_completion?: boolean;

  // Section practice specific
  immediate_feedback: boolean;
  hints_enabled: boolean;
  practice_mode: boolean;
}

export interface QuizFormData {
  lesson_id: number;
  section_id?: number;
  title: string;
  description: string;
  type: QuizType;
  questions: Question[];
  passing_score: number;
  time_limit?: number;
  xp_reward: number;
  order: number;
  is_published: boolean;
  settings: QuizSettings;
}

export const DEFAULT_QUIZ_VALUES: QuizFormData = {
  lesson_id: 0,
  title: '',
  description: '',
  type: 'lesson_assessment',
  questions: [],
  passing_score: 70,
  xp_reward: 50,
  order: 0,
  is_published: false,
  settings: {
    show_feedback: true,
    allow_retry: true,
    shuffle_questions: true,
    show_progress: true,
    require_passing_grade: true,
    unlock_next_lesson: true,
    immediate_feedback: false,
    hints_enabled: false,
    practice_mode: false,
  }
};

export const DEFAULT_SECTION_QUIZ_VALUES: Partial<QuizFormData> = {
  type: 'section_practice',
  passing_score: 60,
  xp_reward: 20,
  settings: {
    show_feedback: true,
    allow_retry: true,
    shuffle_questions: true,
    show_progress: true,
    require_passing_grade: false,
    unlock_next_lesson: false,
    immediate_feedback: true,
    hints_enabled: true,
    practice_mode: true,
  }
};

// Path constants for routing
export const QUIZ_PATHS = {
  // Lesson assessment quiz paths
  newLessonQuiz: (lessonId: string | number) => 
    `/admin/lessons/${lessonId}/quizzes/new`,
  editLessonQuiz: (lessonId: string | number, quizId: string | number) => 
    `/admin/lessons/${lessonId}/quizzes/${quizId}`,
  viewLessonQuiz: (lessonId: string | number, quizId: string | number) => 
    `/admin/lessons/${lessonId}/quizzes/${quizId}/view`,

  // Section practice quiz paths
  newSectionQuiz: (lessonId: string | number, sectionId: string | number) => 
    `/admin/lessons/${lessonId}/sections/${sectionId}/quiz/new`,
  editSectionQuiz: (lessonId: string | number, sectionId: string | number, quizId: string | number) => 
    `/admin/lessons/${lessonId}/sections/${sectionId}/quiz/${quizId}`,
  viewSectionQuiz: (lessonId: string | number, sectionId: string | number, quizId: string | number) => 
    `/admin/lessons/${lessonId}/sections/${sectionId}/quiz/${quizId}/view`,
} as const;