<?php
// Versão optimizada para impressão/Guardar como PDF no navegador.
// O servidor já validou acesso e registou a auditoria antes deste template.
$statusLabels = [
    'pending' => 'Pendente',
    'scheduled' => 'Confirmada',
    'confirmed' => 'Confirmada',
    'waiting' => 'Em espera',
    'in_progress' => 'Em atendimento',
    'completed' => 'Concluída',
    'cancelled' => 'Cancelada',
];
?>
<style>
    /* Estilos autónomos da versão de impressão, sem depender do bundle Tailwind. */
    .record-print { max-width: 920px; margin: 0 auto; padding: 2rem 1.25rem 4rem; color: #21413f; }
    .record-print h1, .record-print h2, .record-print p { margin: 0; }
    .record-print .print-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-bottom: 1.5rem; }
    .record-print .print-button { border: 0; border-radius: .6rem; background: #217d77; color: white; cursor: pointer; font-weight: 700; padding: .7rem 1rem; }
    .record-print .back-button { border: 1px solid #d7cebf; border-radius: .6rem; color: #21413f; font-weight: 700; padding: .7rem 1rem; text-decoration: none; }
    .record-print .record-header { align-items: flex-start; border-bottom: 2px solid #217d77; display: flex; gap: 1rem; justify-content: space-between; padding-bottom: 1rem; }
    .record-print .brand { align-items: center; display: flex; gap: .75rem; }
    .record-print .brand img { height: 42px; width: 42px; }
    .record-print .eyebrow { color: #217d77; font-family: monospace; font-size: .7rem; letter-spacing: .15em; text-transform: uppercase; }
    .record-print .muted { color: #667674; font-size: .82rem; }
    /* A grelha agrupa os dados pessoais para leitura rápida na ficha impressa. */
    .record-print .patient-grid { background: #f5eee3; border-radius: .75rem; display: grid; gap: 1rem; grid-template-columns: repeat(4, 1fr); margin-top: 1.25rem; padding: 1rem; }
    .record-print .label { color: #667674; font-size: .68rem; text-transform: uppercase; }
    .record-print .value { font-size: .9rem; font-weight: 700; margin-top: .25rem; }
    .record-print section { border: 1px solid #e1d8ca; border-radius: .75rem; margin-top: 1.25rem; overflow: hidden; }
    .record-print section h2 { background: #fbf8f1; border-bottom: 1px solid #e1d8ca; font-size: 1rem; padding: .8rem 1rem; }
    .record-print .item { border-bottom: 1px solid #eee7dc; padding: .8rem 1rem; }
    .record-print .item:last-child { border-bottom: 0; }
    .record-print .item-title { font-size: .88rem; font-weight: 700; }
    .record-print .item-meta { color: #667674; font-size: .76rem; margin-top: .25rem; }
    .record-print .item-note { background: #fbf8f1; border-radius: .4rem; font-size: .78rem; margin-top: .5rem; padding: .5rem; }
    .record-print .empty { color: #667674; font-size: .82rem; padding: 1rem; }
    .record-print .footer { color: #667674; font-size: .7rem; margin-top: 1.5rem; text-align: right; }
    @media (max-width: 680px) { .record-print .patient-grid { grid-template-columns: repeat(2, 1fr); } .record-print .record-header { flex-direction: column; } .record-print .print-actions { justify-content: flex-start; } }
    /* Na impressão removemos navegação e acções e evitamos separar secções. */
    @media print { body { background: white !important; } .record-print { max-width: none; padding: 0; } .record-print .no-print { display: none !important; } .record-print section { break-inside: avoid; } .record-print .record-header { border-bottom-color: #21413f; } }
</style>
<main class="record-print">
    <div class="print-actions no-print">
        <a class="back-button" href="<?= url('patient', ['id' => $patient['id']]) ?>">← Voltar à ficha</a>
        <button class="print-button" type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>
    <header class="record-header">
        <div class="brand"><img src="<?= e(asset_url('logo.svg')) ?>" alt=""><div><p class="item-title">Hospital de Malanje</p><p class="muted">Resumo da ficha clínica</p></div></div>
        <div class="muted">Emitido em <?= e((new DateTimeImmutable())->format('d/m/Y H:i')) ?></div>
    </header>
    <div class="patient-grid">
        <div><p class="label">Paciente</p><p class="value"><?= e($patient['name']) ?></p></div>
        <div><p class="label">Nº de processo</p><p class="value"><?= e($patient['medical_record_number']) ?></p></div>
        <div><p class="label">Nascimento / idade</p><p class="value"><?= e((new DateTimeImmutable($patient['birth_date']))->format('d/m/Y')) ?> · <?= age_from_date($patient['birth_date']) ?> anos</p></div>
        <div><p class="label">Estado</p><p class="value"><?= ($patient['active'] ?? true) ? 'Activo' : 'Inactivo' ?></p></div>
        <div><p class="label">Classificação clínica</p><p class="value"><?= e(Patient::typeLabel($patient['patient_type'] ?? 'general')) ?></p></div>
        <div><p class="label">Sexo</p><p class="value"><?= e(['female' => 'Feminino', 'male' => 'Masculino', 'other' => 'Outro', 'not_informed' => 'Não indicado'][$patient['sex']] ?? $patient['sex']) ?></p></div>
        <div><p class="label">Telefone</p><p class="value"><?= e($patient['phone']) ?></p></div>
        <div><p class="label">Bairro</p><p class="value"><?= e($patient['neighborhood'] ?: 'Não indicado') ?></p></div>
    </div>
    <section>
        <h2>Histórico de consultas</h2>
        <?php if (!$appointments): ?><p class="empty">Sem consultas registadas.</p><?php else: ?><?php foreach ($appointments as $appointment): ?><div class="item"><p class="item-title"><?= e($appointment['department_name']) ?> · <?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></p><p class="item-meta"><?= e((new DateTimeImmutable($appointment['date']))->format('d/m/Y')) ?> · <?= e($appointment['time']) ?> · <?= e($appointment['doctor_name']) ?></p><?php if ($appointment['notes']): ?><p class="item-note"><?= e($appointment['notes']) ?></p><?php endif; ?></div><?php endforeach; ?><?php endif; ?>
    </section>
    <?php if ($clinicalAccess): ?>
        <section>
            <h2>Receitas</h2>
            <?php if (!$prescriptions): ?><p class="empty">Sem receitas registadas.</p><?php else: ?><?php foreach ($prescriptions as $prescription): ?><div class="item"><p class="item-title"><?= e($prescription['medication']) ?> · <?= e($prescription['dosage']) ?></p><p class="item-meta"><?= e($prescription['frequency']) ?> · <?= e($prescription['duration']) ?><?php if ($prescription['doctor_name']): ?> · <?= e($prescription['doctor_name']) ?><?php endif; ?></p><?php if ($prescription['instructions']): ?><p class="item-note"><?= e($prescription['instructions']) ?></p><?php endif; ?></div><?php endforeach; ?><?php endif; ?>
        </section>
        <section>
            <h2>Resultados clínicos</h2>
            <?php if (!$results): ?><p class="empty">Sem resultados registados.</p><?php else: ?><?php foreach ($results as $result): ?><div class="item"><p class="item-title"><?= e($result['title']) ?></p><p class="item-meta"><?= e((new DateTimeImmutable($result['created_at']))->format('d/m/Y H:i')) ?><?php if ($result['doctor_name']): ?> · <?= e($result['doctor_name']) ?><?php endif; ?></p><p class="item-note"><?= nl2br(e($result['result_text'])) ?></p></div><?php endforeach; ?><?php endif; ?>
        </section>
        <section>
            <h2>Documentos registados</h2>
            <?php if (!$documents): ?><p class="empty">Sem documentos registados.</p><?php else: ?><?php foreach ($documents as $document): ?><div class="item"><p class="item-title"><?= e($document['title']) ?></p><p class="item-meta"><?= e($document['file_name']) ?> · <?= number_format((int) $document['file_size'] / 1024, 0) ?> KB · <?= e((new DateTimeImmutable($document['created_at']))->format('d/m/Y')) ?></p></div><?php endforeach; ?><?php endif; ?>
        </section>
    <?php endif; ?>
    <p class="footer">Documento confidencial · Acesso sujeito às permissões do Hospital de Malanje.</p>
</main>