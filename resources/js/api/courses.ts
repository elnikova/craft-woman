import { http } from './http';
import type {
    CertificateView,
    CourseCard,
    CourseDetail,
    CourseEditor,
    CoursePayload,
    CourseProgress,
    CourseStatus,
    LearningItem,
    LessonView,
    MaterialType,
} from '@/types/course';
import type { Paginated, Wrapped } from '@/types/user';

const unwrap = <T>(p: Promise<{ data: Wrapped<T> }>) => p.then((r) => r.data.data);
type Progress = CourseProgress & { completed_at: string | null };

export const coursesApi = {
    async list(params: { q?: string; category?: string; maker?: string; sort?: string; page?: number; per_page?: number } = {}): Promise<Paginated<CourseCard>> {
        const { data } = await http.get<Paginated<CourseCard>>('/courses', { params });
        return data;
    },
    show: (slug: string) => unwrap<CourseDetail>(http.get(`/courses/${slug}`)),
    lesson: (slug: string, lesson: string) => unwrap<LessonView>(http.get(`/courses/${slug}/lessons/${lesson}`)),
    fileUrl: (slug: string, lesson: string, material: string) => `/api/v1/courses/${slug}/lessons/${lesson}/materials/${material}/file`,
    complete: (slug: string, lesson: string) => unwrap<Progress>(http.post(`/courses/${slug}/lessons/${lesson}/complete`)),
    uncomplete: (slug: string, lesson: string) => unwrap<Progress>(http.delete(`/courses/${slug}/lessons/${lesson}/complete`)),
    certificate: (slug: string) => unwrap<CertificateView>(http.post(`/courses/${slug}/certificate`)),
    verify: (number: string) => unwrap<CertificateView>(http.get(`/certificates/${encodeURIComponent(number)}`)),
    learning: () => unwrap<LearningItem[]>(http.get('/me/learning')),

    // ---- maker ----
    async mine(status?: CourseStatus): Promise<Paginated<CourseCard>> {
        const { data } = await http.get<Paginated<CourseCard>>('/me/courses', { params: status ? { status } : {} });
        return data;
    },
    mineOne: (uuid: string) => unwrap<CourseEditor>(http.get(`/me/courses/${uuid}`)),
    create: (payload: CoursePayload) => unwrap<CourseEditor>(http.post('/me/courses', payload)),
    update: (uuid: string, payload: Partial<CoursePayload>) => unwrap<CourseEditor>(http.put(`/me/courses/${uuid}`, payload)),
    remove: (uuid: string) => http.delete(`/me/courses/${uuid}`),
    cover(uuid: string, file: File) {
        const form = new FormData();
        form.append('cover', file);
        return unwrap<CourseEditor>(http.post(`/me/courses/${uuid}/cover`, form));
    },
    submit: (uuid: string) => unwrap<CourseEditor>(http.post(`/me/courses/${uuid}/submit`)),
    archive: (uuid: string) => unwrap<CourseEditor>(http.post(`/me/courses/${uuid}/archive`)),
    relist: (uuid: string) => unwrap<CourseEditor>(http.post(`/me/courses/${uuid}/relist`)),

    addModule: (course: string, title: string) => unwrap<CourseEditor>(http.post(`/me/courses/${course}/modules`, { title })),
    updateModule: (module: string, payload: { title?: string; description?: string | null }) =>
        unwrap<CourseEditor>(http.patch(`/me/course-modules/${module}`, payload)),
    deleteModule: (module: string) => unwrap<CourseEditor>(http.delete(`/me/course-modules/${module}`)),
    reorderModules: (course: string, order: string[]) => unwrap<CourseEditor>(http.post(`/me/courses/${course}/modules/reorder`, { order })),
    addLesson: (module: string, title: string) => unwrap<CourseEditor>(http.post(`/me/course-modules/${module}/lessons`, { title })),
    updateLesson: (lesson: string, payload: { title?: string; description?: string | null; is_required?: boolean; is_preview?: boolean }) =>
        unwrap<CourseEditor>(http.patch(`/me/lessons/${lesson}`, payload)),
    deleteLesson: (lesson: string) => unwrap<CourseEditor>(http.delete(`/me/lessons/${lesson}`)),
    reorderLessons: (module: string, order: string[]) => unwrap<CourseEditor>(http.post(`/me/course-modules/${module}/lessons/reorder`, { order })),
    addMaterial(lesson: string, payload: { type: MaterialType; title?: string | null; content?: string | null; external_url?: string | null; file?: File | null }, onProgress?: (pct: number) => void) {
        const form = new FormData();
        for (const [k, v] of Object.entries(payload)) if (v !== null && v !== undefined) form.append(k, v as string | Blob);
        return unwrap<CourseEditor>(
            http.post(`/me/lessons/${lesson}/materials`, form, {
                onUploadProgress: (e) => onProgress?.(e.total ? Math.round((e.loaded * 100) / e.total) : 0),
            }),
        );
    },
    updateMaterial: (material: string, payload: { title?: string | null; content?: string | null; external_url?: string | null }) =>
        unwrap<CourseEditor>(http.patch(`/me/lesson-materials/${material}`, payload)),
    deleteMaterial: (material: string) => unwrap<CourseEditor>(http.delete(`/me/lesson-materials/${material}`)),

    // ---- admin ----
    async review(status: CourseStatus): Promise<Paginated<CourseEditor>> {
        const { data } = await http.get<Paginated<CourseEditor>>('/admin/courses', { params: { status } });
        return data;
    },
    approve: (uuid: string) => unwrap<CourseEditor>(http.post(`/admin/courses/${uuid}/approve`)),
    reject: (uuid: string, note: string) => unwrap<CourseEditor>(http.post(`/admin/courses/${uuid}/reject`, { note })),
    archiveAsAdmin: (uuid: string, note: string) => unwrap<CourseEditor>(http.post(`/admin/courses/${uuid}/archive`, { note })),
};
