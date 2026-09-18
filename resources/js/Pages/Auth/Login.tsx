import React from 'react';
import { useForm, router, Head } from '@inertiajs/react';
import { Lock, Mail, UserCheck, Shield } from 'lucide-react';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Card, CardContent } from '@/Components/Card';

interface DemoUser {
    id: number;
    name: string;
    email: string;
    role: string;
    unit: string;
}

interface LoginProps {
    demoUsers: DemoUser[];
}

export default function Login({ demoUsers = [] }: LoginProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    const handleQuickLogin = (email: string) => {
        setData({
            email,
            password: 'password',
            remember: true,
        });
        router.post('/login', {
            email,
            password: 'password',
            remember: true,
        });
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-100 via-white to-blue-50/50 flex flex-col justify-center items-center p-4 font-sans text-slate-800">
            <Head title="Masuk Sistem" />

            <div className="w-full max-w-md">
                {/* Header Instansi */}
                <div className="text-center mb-6">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-[#122E92] to-[#0a1b5c] text-white shadow-xl mb-3 border-2 border-[#D6AC48]">
                        <span className="text-2xl font-black tracking-wider">S</span>
                    </div>
                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
                        SAKIP LLDIKTI XVI
                    </h1>
                    <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        Sistem Akuntabilitas Kinerja Instansi Pemerintah Lembaga Layanan Pendidikan Tinggi Wilayah XVI
                    </p>
                </div>

                {/* Form Login Card */}
                <Card className="shadow-lg border-slate-200/80">
                    <CardContent className="pt-6">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <Input
                                    label="Alamat Email Kedinasan"
                                    type="email"
                                    name="email"
                                    placeholder="nama@lldikti16.kemdikbud.go.id"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    error={errors.email}
                                    required
                                />
                            </div>

                            <div>
                                <Input
                                    label="Kata Sandi"
                                    type="password"
                                    name="password"
                                    placeholder="••••••••"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    error={errors.password}
                                    required
                                />
                            </div>

                            <div className="flex items-center justify-between text-xs">
                                <label className="flex items-center gap-2 cursor-pointer text-slate-600 select-none">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="rounded border-slate-300 text-[#122E92] focus:ring-[#122E92]"
                                    />
                                    Ingat saya di perangkat ini
                                </label>
                            </div>

                            <Button
                                type="submit"
                                variant="primary"
                                className="w-full"
                                isLoading={processing}
                            >
                                <Lock className="w-4 h-4 mr-1" />
                                Masuk ke Sistem
                            </Button>
                        </form>

                        {/* Quick Demo Login Buttons (Development Mode) */}
                        {demoUsers.length > 0 && (
                            <div className="mt-6 pt-5 border-t border-slate-100">
                                <div className="flex items-center gap-1.5 text-xs font-semibold text-slate-700 mb-2.5">
                                    <Shield className="w-3.5 h-3.5 text-[#122E92]" />
                                    <span>Pilih Akun Demo (1-Click Login):</span>
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    {demoUsers.map((u) => (
                                        <button
                                            key={u.id}
                                            type="button"
                                            onClick={() => handleQuickLogin(u.email)}
                                            className="text-left p-2 rounded-lg border border-slate-200 hover:border-[#122E92] hover:bg-blue-50/50 transition-all text-xs group cursor-pointer"
                                        >
                                            <div className="font-semibold text-slate-800 capitalize group-hover:text-[#122E92]">
                                                {u.role}
                                            </div>
                                            <div className="text-[11px] text-slate-500 truncate">
                                                {u.name}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Footer info */}
                <div className="text-center mt-6 text-[11px] text-slate-400">
                    &copy; 2026 LLDIKTI Wilayah XVI (Gorontalo, Sulawesi Utara, Sulawesi Tengah).
                </div>
            </div>
        </div>
    );
}
