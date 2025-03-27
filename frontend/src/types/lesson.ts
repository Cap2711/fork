import { Section } from './section';
import { Quiz } from './quiz';

export interface Lesson {
  id: number;
  unit_id: number;
  title: string;
  slug: string;
  description: string;
  order: number;
  is_published: boolean;
  estimated_time: number;
  xp_reward: number;
  difficulty_level: string;
  sections: Section[];
  assessment_quiz?: Quiz;
  created_at: string;
  updated_at: string;
}

export interface LessonFormData {
  id?: number;
  unit_id: number;
  title: string;
  description: string;
  order: number;
  is_published: boolean;
  estimated_time: number;
  xp_reward: number;
  difficulty_level: string;
}

export const DEFAULT_LESSON_VALUES: LessonFormData = {
  unit_id: 0,
  title: '',
  description: '',
  order: 0,
  is_published: false,
  estimated_time: 10,
  xp_reward: 10,
  difficulty_level: 'beginner',
};

// Type guard to check if a section has a quiz
export function hasQuiz(section: Section): boolean {
  return !!section.quiz;
}

// Helper function to check if a lesson has an assessment quiz
export function hasAssessmentQuiz(lesson: Lesson): boolean {
  return !!lesson.assessment_quiz;
}