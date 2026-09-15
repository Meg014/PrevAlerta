<span class="mb-5 inline-flex rounded-xl bg-brand-50 p-3 text-brand-700"><?= $this->element('icon', ['name' => 'shield', 'class' => 'size-7']) ?></span>
<h2 class="page-title">Bem-vindo de volta</h2><p class="muted mb-8 mt-2">Entre com sua conta para acessar os checklists.</p>
<?= $this->Form->create(null) ?>
<?= $this->Form->control('email', ['label' => 'E-mail', 'type' => 'email', 'required' => true, 'autocomplete' => 'username', 'placeholder' => 'voce@empresa.com.br']) ?>
<?= $this->Form->control('password', ['label' => 'Senha', 'required' => true, 'autocomplete' => 'current-password', 'value' => '']) ?>
<?= $this->Form->button('Entrar na minha conta →', ['class' => 'btn w-full']) ?><?= $this->Form->end() ?>
<p class="mt-7 text-center text-xs leading-relaxed text-slate-500">Precisa de acesso? Entre em contato com o administrador da sua equipe.</p>
