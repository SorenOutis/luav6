export type StudentPageMode = 'enabled' | 'blurred' | 'disabled';

export type StudentPageControl = {
    label: string;
    path: string;
    mode: StudentPageMode;
    message: string | null;
};

export type CurrentStudentPageControl = {
    key: string;
    label: string;
    mode: StudentPageMode;
    message: string | null;
};

export type StudentPageControls = {
    pages: Record<string, StudentPageControl>;
    current: CurrentStudentPageControl | null;
};
