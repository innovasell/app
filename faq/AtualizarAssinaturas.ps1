# 1. Conectar ao Exchange Online (vai abrir janela de login)
Connect-ExchangeOnline

# 2. Definições Gerais
# COLOQUE O LINK DA SUA LOGO AQUI (Tem que ser HTTPS público)
$LinkDaSuaLogo = "https://seusite.com.br/logo.png" 
$NomeDaEmpresa = "Infini Solutions"

# 3. Obter todos os usuários (Use -ResultSize Unlimited se tiver muitos usuários)
Write-Host "Lendo usuários..." -ForegroundColor Cyan
#$Usuarios = Get-Mailbox -RecipientTypeDetails UserMailbox -ResultSize Unlimited
$Usuarios = Get-Mailbox -Identity "hector.hansen@innovasell.com.br"

# 4. Loop para processar cada usuário
foreach ($User in $Usuarios) {
    
    # Pega detalhes estendidos do usuário (Cargo, Telefone, etc)
    $UserDetails = Get-User -Identity $User.Alias
    
    # Tratamento simples para campos vazios (Evita ficar buracos na assinatura)
    $Nome   = if ($UserDetails.DisplayName) { $UserDetails.DisplayName } else { "Colaborador" }
    $Cargo  = if ($UserDetails.Title) { $UserDetails.Title } else { "Colaborador" }
    $Phone  = if ($UserDetails.Phone) { $UserDetails.Phone } else { "" }
    $Email  = $User.PrimarySmtpAddress

    Write-Host "Atualizando assinatura de: $Nome" -ForegroundColor Green

    # 5. Montagem do HTML
    # Aqui recriamos o layout da sua imagem
    $HtmlSignature = @"
    <div style="font-family: Arial, sans-serif; font-size: 11pt; color: #333;">
        <br>
        <strong>$Nome</strong><br>
        <span style="font-style: italic;">$Cargo</span> | $NomeDaEmpresa<br>
        📞 $Phone<br>
        📧 <a href="mailto:$Email" style="color: #0000EE; text-decoration: none;">$Email</a><br>
        <br>
        <img src="$LinkDaSuaLogo" alt="Logo" width="120" height="auto">
    </div>
"@

    # 6. Aplica a configuração na caixa do usuário
    # -SignatureHtml: Define o código HTML
    # -AutoAddSignature $true: Força a assinatura a aparecer automaticamente em novos emails
    # -AutoAddSignatureOnReply $true: Força a aparecer em respostas (RESOLVE SEU PROBLEMA)
    
    try {
        Set-MailboxMessageConfiguration -Identity $User.Alias `
            -SignatureHtml $HtmlSignature `
            -AutoAddSignature $true `
            -AutoAddSignatureOnReply $true `
            -ErrorAction Stop
    }
    catch {
        Write-Host "Erro ao atualizar $Nome: $_" -ForegroundColor Red
    }
}

Write-Host "Processo finalizado!" -ForegroundColor Yellow