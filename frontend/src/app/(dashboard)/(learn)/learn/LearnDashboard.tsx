"use client"

import { useState } from "react"
import { BookOpen, Crown, FlameIcon as Fire, Lightbulb, Star, User, Users, BookText, GraduationCap } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"

export default function Dashboard() {
  const [activeTab, setActiveTab] = useState("path")

  // Mock user data
  const userData = {
    name: "Alex Johnson",
    points: 2450,
    streak: 7,
    currentPath: 4,
    completedPaths: [1, 2, 3],
    inProgressPaths: [4],
  }

  // Mock learning path data
  const learningPaths = [
    {
      id: 1,
      name: "Beginner I",
      description: "Start your language journey with basic expressions",
      units: 3,
      unitsCompleted: 3,
      unlocked: true,
    },
    {
      id: 2,
      name: "Beginner II",
      description: "Build on your foundation with simple conversations",
      units: 3,
      unitsCompleted: 3,
      unlocked: true,
    },
    {
      id: 3,
      name: "Elementary I",
      description: "Expand your vocabulary with everyday topics",
      units: 3,
      unitsCompleted: 3,
      unlocked: true,
    },
    {
      id: 4,
      name: "Elementary II",
      description: "Learn to discuss family, hobbies, and daily life",
      units: 4,
      unitsCompleted: 2,
      unlocked: true,
    },
    {
      id: 5,
      name: "Intermediate I",
      description: "Express opinions and discuss various topics",
      units: 3,
      unitsCompleted: 0,
      unlocked: true,
    },
    {
      id: 6,
      name: "Intermediate II",
      description: "Improve fluency with complex conversations",
      units: 4,
      unitsCompleted: 0,
      unlocked: false,
    },
    {
      id: 7,
      name: "Upper Intermediate I",
      description: "Discuss abstract topics and current events",
      units: 3,
      unitsCompleted: 0,
      unlocked: false,
    },
    {
      id: 8,
      name: "Upper Intermediate II",
      description: "Express nuanced opinions and hypothetical situations",
      units: 4,
      unitsCompleted: 0,
      unlocked: false,
    },
    {
      id: 9,
      name: "Advanced I",
      description: "Understand complex texts and specialized vocabulary",
      units: 3,
      unitsCompleted: 0,
      unlocked: false,
    },
    {
      id: 10,
      name: "Advanced II",
      description: "Communicate with near-native fluency on complex topics",
      units: 4,
      unitsCompleted: 0,
      unlocked: false,
    },
    {
      id: 11,
      name: "Proficiency I",
      description: "Master subtle nuances and cultural references",
      units: 4,
      unitsCompleted: 0,
      unlocked: false,
    },
    {
      id: 12,
      name: "Proficiency II",
      description: "Achieve near-native mastery of the language",
      units: 5,
      unitsCompleted: 0,
      unlocked: false,
    },
  ]

  // Mock vocabulary data
  const recentVocabulary = [
    { word: "Family", translation: "A group of related people", mastery: 80 },
    { word: "Sibling", translation: "A brother or sister", mastery: 65 },
    { word: "Cousin", translation: "A child of one's aunt or uncle", mastery: 45 },
    { word: "Nephew", translation: "A son of one's sibling", mastery: 30 },
    { word: "Grandparent", translation: "A parent of one's parent", mastery: 90 },
  ]

  // Mock leaderboard data
  const leaderboardData = [
    { id: 1, name: "Sarah K.", points: 3250, avatar: "/placeholder.svg?height=40&width=40" },
    { id: 2, name: "Mike T.", points: 3100, avatar: "/placeholder.svg?height=40&width=40" },
    { id: 3, name: "Alex J.", points: 2450, avatar: "/placeholder.svg?height=40&width=40" },
    { id: 4, name: "Jessica L.", points: 2300, avatar: "/placeholder.svg?height=40&width=40" },
    { id: 5, name: "David R.", points: 2150, avatar: "/placeholder.svg?height=40&width=40" },
  ]

  // Mock guide book categories
  const guideBookCategories = [
    { name: "Grammar", icon: <BookText className="w-5 h-5" />, count: 24 },
    { name: "Pronunciation", icon: <Mic className="w-5 h-5" />, count: 18 },
    { name: "Cultural Notes", icon: <Lightbulb className="w-5 h-5" />, count: 15 },
    { name: "Learning Tips", icon: <GraduationCap className="w-5 h-5" />, count: 12 },
  ]

  return (
    <div className="min-h-screen bg-background">
      <header className="sticky top-0 z-10 border-b bg-background">
        <div className="container flex items-center justify-between h-16 px-4 md:px-6">
          <div className="flex items-center gap-2 text-xl font-bold">
            <BookOpen className="w-6 h-6 text-primary" />
            <span>EnglishQuest</span>
          </div>
          <div className="flex items-center gap-4">
            <div className="flex items-center gap-1 text-amber-500">
              <Star className="w-5 h-5 fill-amber-500 text-amber-500" />
              <span className="font-medium">{userData.points}</span>
            </div>
            <div className="flex items-center gap-1 text-orange-500">
              <Fire className="w-5 h-5 text-orange-500 fill-orange-500" />
              <span className="font-medium">{userData.streak}</span>
            </div>
            <Button variant="ghost" size="icon" className="rounded-full">
              <User className="w-5 h-5" />
              <span className="sr-only">User profile</span>
            </Button>
          </div>
        </div>
      </header>
      <main className="container px-4 py-6 md:px-6 md:py-8">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold md:text-3xl">Welcome back, {userData.name}!</h1>
          <div className="flex items-center gap-2">
            <Crown className="w-5 h-5 text-amber-500" />
            <span className="font-medium">
              Path {userData.currentPath}: {learningPaths[userData.currentPath - 1].name}
            </span>
          </div>
        </div>
         <Tabs defaultValue="path" className="mb-8" onValueChange={(value) => setActiveTab(value)}>
          <TabsList className="grid w-full max-w-md grid-cols-4">
            <TabsTrigger value="path">Learning Path</TabsTrigger>
            <TabsTrigger value="vocabulary">Vocabulary</TabsTrigger>
            <TabsTrigger value="guidebook">Guide Book</TabsTrigger>
            <TabsTrigger value="leaderboard">Leaderboard</TabsTrigger>
          </TabsList>
          <TabsContent value="path" className="mt-6">
            <div className="mb-6">
              <h2 className="mb-2 text-xl font-bold">Your Learning Journey</h2>
              <p className="text-muted-foreground">
                Follow the learning path to master English step by step. Complete each path to unlock the next one.
              </p>
            </div>

            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
              {learningPaths.map((path) => (
                <LearningPathCard
                  key={path.id}
                  path={path}
                  isCompleted={userData.completedPaths.includes(path.id)}
                  isInProgress={userData.inProgressPaths.includes(path.id)}
                />
              ))}
            </div>
          </TabsContent>

          <TabsContent value="vocabulary" className="mt-6">
            <div className="mb-6">
              <h2 className="mb-2 text-xl font-bold">Your Vocabulary</h2>
              <p className="text-muted-foreground">Track your vocabulary progress and review words you&apos;ve learned.</p>
            </div>

            <div className="grid gap-6 md:grid-cols-2">
              <Card>
                <CardContent className="p-6">
                  <h3 className="mb-4 text-lg font-bold">Recently Learned Words</h3>
                  <div className="space-y-4">
                    {recentVocabulary.map((item, index) => (
                      <div key={index} className="p-3 border rounded-md">
                        <div className="flex items-center justify-between mb-2">
                          <div>
                            <h4 className="font-medium">{item.word}</h4>
                            <p className="text-sm text-muted-foreground">{item.translation}</p>
                          </div>
                          <Button variant="ghost" size="sm" className="w-8 h-8 p-0">
                            <Volume2 className="w-4 h-4" />
                          </Button>
                        </div>
                        <div className="flex items-center gap-2">
                          <Progress value={item.mastery} className="h-2" />
                          <span className="text-xs font-medium">{item.mastery}%</span>
                        </div>
                      </div>
                    ))}
                  </div>
                  <div className="mt-4">
                    <Button variant="outline" className="w-full">
                      View All Vocabulary
                    </Button>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardContent className="p-6">
                  <h3 className="mb-4 text-lg font-bold">Vocabulary Statistics</h3>
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <span>Total Words Learned</span>
                      <span className="font-medium">247</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span>Mastered Words</span>
                      <span className="font-medium">183</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span>Words to Review</span>
                      <span className="font-medium">64</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span>Average Mastery</span>
                      <span className="font-medium">74%</span>
                    </div>
                  </div>
                  <div className="mt-6">
                    <Button className="w-full">Practice Vocabulary</Button>
                  </div>
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          <TabsContent value="guidebook" className="mt-6">
            <div className="mb-6">
              <h2 className="mb-2 text-xl font-bold">Guide Book</h2>
              <p className="text-muted-foreground">
                Reference materials to help you understand grammar, pronunciation, and cultural aspects.
              </p>
            </div>

            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
              {guideBookCategories.map((category, index) => (
                <Card key={index} className="transition-shadow hover:shadow-md">
                  <CardContent className="p-6 text-center">
                    <div className="flex justify-center mb-4">
                      <div className="p-4 rounded-full bg-primary/10">{category.icon}</div>
                    </div>
                    <h3 className="mb-1 font-bold">{category.name}</h3>
                    <p className="mb-4 text-sm text-muted-foreground">{category.count} topics</p>
                    <Button variant="outline" className="w-full">
                      Browse
                    </Button>
                  </CardContent>
                </Card>
              ))}
            </div>

            <div className="mt-6">
              <Card>
                <CardContent className="p-6">
                  <h3 className="mb-4 text-lg font-bold">Recently Viewed Topics</h3>
                  <div className="space-y-2">
                    <div className="flex items-center justify-between p-3 border rounded-md hover:bg-accent">
                      <div className="flex items-center gap-3">
                        <BookText className="w-5 h-5 text-primary" />
                        <span>Present Simple Tense</span>
                      </div>
                      <ChevronRight className="w-5 h-5 text-muted-foreground" />
                    </div>
                    <div className="flex items-center justify-between p-3 border rounded-md hover:bg-accent">
                      <div className="flex items-center gap-3">
                        <Lightbulb className="w-5 h-5 text-amber-500" />
                        <span>Greetings in Different Cultures</span>
                      </div>
                      <ChevronRight className="w-5 h-5 text-muted-foreground" />
                    </div>
                    <div className="flex items-center justify-between p-3 border rounded-md hover:bg-accent">
                      <div className="flex items-center gap-3">
                        <Mic className="w-5 h-5 text-red-500" />
                        <span>Difficult Sounds in English</span>
                      </div>
                      <ChevronRight className="w-5 h-5 text-muted-foreground" />
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          <TabsContent value="leaderboard" className="mt-6">
            <div className="border rounded-lg">
              <div className="p-4 bg-muted/50">
                <h2 className="flex items-center gap-2 text-xl font-bold">
                  <Medal className="w-5 h-5 text-amber-500" />
                  Weekly Leaderboard
                </h2>
              </div>
              <div className="divide-y">
                {leaderboardData.map((user, index) => (
                  <div
                    key={user.id}
                    className={`flex items-center justify-between p-4 ${index === 2 ? "bg-primary/5" : ""}`}
                  >
                    <div className="flex items-center gap-3">
                      <div className="flex items-center justify-center w-8 h-8 font-semibold rounded-full bg-muted">
                        {index + 1}
                      </div>
                      <div className="flex items-center gap-3">
                        <div className="relative w-10 h-10 overflow-hidden rounded-full">
                          <Image 
                            src={user.avatar || "/placeholder.svg"} 
                            alt={user.name} 
                            className="object-cover" 
                            fill
                            sizes="40px"
                          />
                        </div>
                        <div>
                          <p className="font-medium">{user.name}</p>
                          <p className="text-sm text-muted-foreground">Path {index + 3}</p>
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center gap-1 text-amber-500">
                      <Star className="w-4 h-4 fill-amber-500 text-amber-500" />
                      <span className="font-medium">{user.points}</span>
                    </div>
                  </div>
                ))}
              </div>
              <div className="flex justify-center p-4 border-t">
                <Button variant="outline" className="gap-2">
                  <Users className="w-4 h-4" />
                  <span>View Full Leaderboard</span>
                </Button>
              </div>
            </div>
          </TabsContent>
        </Tabs>

        <div className="mt-8">
          <h2 className="mb-4 text-xl font-bold">Daily Goal</h2>
          <div className="flex items-center gap-4">
            <Progress value={60} className="w-full h-3" />
            <span className="text-sm font-medium">60%</span>
          </div>
        </div>
      </main>
    </div>
  )
}

  

import { ChevronRight, Medal, Mic, Volume2 } from "lucide-react"
import LearningPathCard from "./LearningPathCard"
import Image from "next/image"

